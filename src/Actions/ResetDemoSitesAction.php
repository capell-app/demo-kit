<?php

declare(strict_types=1);

namespace Capell\DemoKit\Actions;

use Capell\Core\Models\Site;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @method static int run(list<string> $siteNames)
 */
final class ResetDemoSitesAction
{
    use AsAction;

    /**
     * @param  list<string>  $siteNames
     */
    public function handle(array $siteNames): int
    {
        $siteNames = array_values(array_unique(array_filter(
            array_map(trim(...), $siteNames),
            static fn (string $siteName): bool => $siteName !== '',
        )));

        if ($siteNames === []) {
            return 0;
        }

        $deleted = 0;

        Site::query()
            ->whereIn('name', $siteNames)
            ->eachById(function (Site $site) use (&$deleted): void {
                $site->delete();

                $deleted++;
            });

        return $deleted;
    }
}
