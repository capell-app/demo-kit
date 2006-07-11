<?php

declare(strict_types=1);

namespace Capell\DemoKit\Actions;

use Capell\DemoKit\Support\DemoKitPermissions;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

final class ResetDemoKitProvenanceAction
{
    use AsFake;
    use AsObject;

    /** @param list<string> $siteNames */
    public function handle(array $siteNames, ?Model $actor = null): int
    {
        if (! app()->environment(['local', 'testing'])) {
            throw ValidationException::withMessages([
                'sites' => __('capell-demo-kit::actions.example_site_data_environment_blocked'),
            ]);
        }

        DemoKitPermissions::authorize($actor instanceof Authenticatable ? $actor : null);

        $provenanceSites = ListDemoKitProvenanceSitesAction::run($actor)
            ->whereIn('name', $siteNames)
            ->pluck('name')
            ->map(static fn (mixed $name): string => is_string($name) ? $name : '')
            ->values()
            ->all();

        /** @var list<string> $provenanceSiteNames */
        $provenanceSiteNames = array_values($provenanceSites);

        return ResetDemoSitesAction::run($provenanceSiteNames);
    }
}
