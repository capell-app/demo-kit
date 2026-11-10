<?php

declare(strict_types=1);

namespace Capell\DemoKit\Actions;

use Capell\Admin\Filament\Resources\Sites\SiteResource;
use Capell\Core\Actions\ResolvePublicPageByUrlAction;
use Capell\Core\Actions\SiteDomains\ResolveSiteDomainAction;
use Capell\Core\Actions\SiteDomains\ResolveSiteDomainUrlAction;
use Capell\Core\Data\SiteDomains\SiteRequestTargetData;
use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Capell\Core\Models\SiteDomain;
use Capell\Core\Support\Permissions\SiteAccess;
use Capell\DemoKit\Support\DemoKitPermissions;
use Illuminate\Contracts\Database\Eloquent\Builder;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/** @method static array{name: string, edit_url: ?string, public_url: ?string} run(array<string, mixed> $content) */
final class ResolveDemoKitResultAction
{
    use AsFake;
    use AsObject;

    /**
     * @param  array<string, mixed>  $content
     * @return array{name: string, edit_url: ?string, public_url: ?string}
     */
    public function handle(array $content): array
    {
        $name = is_string($content['name'] ?? null) ? $content['name'] : '';
        $result = ['name' => '', 'edit_url' => null, 'public_url' => null];
        $id = $content['site_id'] ?? null;
        if (config('capell-demo-kit.result_links') !== true || ! DemoKitPermissions::canManage(auth()->user()) || ! is_int($id)) {
            return $result;
        }

        $site = Site::query()->find($id);
        if (! $site instanceof Site || $site->name !== $name || ! SiteAccess::current()->can($site) || ! HasDemoSiteProvenanceAction::run($site)) {
            return $result;
        }

        $result['name'] = $name;
        if (SiteResource::canEdit($site)) {
            $result['edit_url'] = SiteResource::getUrl('edit', ['record' => $site]);
        }

        if (! $site->status || data_get($site->meta, 'is_preview') === true) {
            return $result;
        }

        $sites = Site::query()->whereHas('siteDomains', fn (Builder $query): Builder => $query->enabled()->whereHas('language', fn (Builder $language): Builder => $language->enabled()))
            ->with(['siteDomains.language', 'translations'])->get();
        $current = $sites->first(static fn (Site $site): bool => $site->getKey() === $id);
        if (! $current instanceof Site) {
            return $result;
        }

        foreach ($current->siteDomains as $domain) {
            $language = $domain->language;
            if (! $domain instanceof SiteDomain || ! $domain->status || ! $language instanceof Language || ! $language->status
                || ! $current->translations()->where('language_id', $language->getKey())->exists()) {
                continue;
            }

            $url = ResolveSiteDomainUrlAction::run($domain, '/');
            try {
                $resolved = ResolveSiteDomainAction::run(SiteRequestTargetData::fromUrl($url), $sites);
            } catch (InvalidArgumentException) {
                continue;
            }
            if ($resolved === null || ! $resolved->siteDomain->is($domain) || $resolved->relativePath !== '/') {
                continue;
            }
            if (ResolvePublicPageByUrlAction::run($current, $language, '/')->found()) {
                $result['public_url'] = $url;

                break;
            }
        }

        return $result;
    }
}
