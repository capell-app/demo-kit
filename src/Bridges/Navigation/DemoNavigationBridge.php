<?php

declare(strict_types=1);

namespace Capell\DemoKit\Bridges\Navigation;

use Capell\Core\Facades\CapellCore;
use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Navigation\Models\Navigation;
use Capell\Navigation\Support\Creator\NavigationCreator;
use Capell\Navigation\Support\Creator\NavigationDemoCreator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;

/**
 * The only place Demo Kit touches the optional Navigation package. Every method
 * returns without touching Navigation unless it is installed.
 */
final class DemoNavigationBridge
{
    /**
     * @param  EloquentCollection<int, Language>  $languages
     */
    public static function setupSiteNavigations(Site $site, EloquentCollection $languages, Page $homePage): void
    {
        if (! (CapellCore::isPackageAvailable('capell-app/navigation') && CapellCore::isPackageInstalled('capell-app/navigation'))) {
            return;
        }

        $navigationDemoCreator = new NavigationDemoCreator;

        $languages->each(function (Language $language) use ($navigationDemoCreator, $site, $homePage): void {
            $navigationDemoCreator->setupMainNavigation($site, $language, $homePage);
            $navigationDemoCreator->setupFooterNavigation($site, $language);
            $navigationDemoCreator->setupSubFooterNavigation($site, $language);
        });
    }

    /**
     * @param  array<mixed>  $items
     */
    public static function upsertNavigation(string $key, string $name, int $siteId, int $blueprintId, array $items): ?Model
    {
        if (! (CapellCore::isPackageAvailable('capell-app/navigation') && CapellCore::isPackageInstalled('capell-app/navigation'))) {
            return null;
        }

        return Navigation::query()->updateOrCreate([
            'key' => $key,
            'site_id' => $siteId,
            'blueprint_id' => $blueprintId,
        ], [
            'name' => $name,
            'items' => $items,
        ]);
    }

    public static function pageNavigationLabel(Page $page, Language $language): ?string
    {
        if (! (CapellCore::isPackageAvailable('capell-app/navigation') && CapellCore::isPackageInstalled('capell-app/navigation'))) {
            return null;
        }

        return NavigationCreator::getPageNavigationLabel($page, $language);
    }
}
