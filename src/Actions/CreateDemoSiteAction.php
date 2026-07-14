<?php

declare(strict_types=1);

namespace Capell\DemoKit\Actions;

use Capell\Core\Actions\CreateSiteAction;
use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsObject;
use RuntimeException;

/**
 * @method static Site run(string $name, string $url, Language $language, Collection<int, Language> $languages, bool $adoptExistingSite = false)
 */
final class CreateDemoSiteAction
{
    use AsObject;

    /**
     * @param  Collection<int, Language>  $languages
     */
    public function handle(
        string $name,
        string $url,
        Language $language,
        Collection $languages,
        bool $adoptExistingSite = false,
    ): Site {
        $existingSite = Site::query()->where('name', $name)->first();

        if ($existingSite instanceof Site && ! HasDemoSiteProvenanceAction::run($existingSite)) {
            if ($adoptExistingSite) {
                return MarkDemoSiteProvenanceAction::run($existingSite);
            }

            throw new RuntimeException(sprintf(
                'Refusing to replace site [%s] because it was not provisioned by Demo Kit.',
                $name,
            ));
        }

        $site = (new CreateSiteAction)->handle(
            $name,
            url: $url,
            language: $language,
            languages: $languages,
        );

        return (new MarkDemoSiteProvenanceAction)->handle($site);
    }
}
