<?php

declare(strict_types=1);

namespace Capell\DemoKit\Bridges\HtmlCache;

use Capell\Core\Facades\CapellCore;
use Capell\HtmlCache\Actions\BuildHtmlCacheEligibilityReportAction;

/**
 * The only place Demo Kit touches the optional HTML Cache package.
 */
final class DemoHtmlCacheBridge
{
    /** @return class-string<BuildHtmlCacheEligibilityReportAction>|null */
    public static function eligibilityReportAction(): ?string
    {
        if (! CapellCore::isPackageAvailable('capell-app/html-cache')) {
            return null;
        }

        return BuildHtmlCacheEligibilityReportAction::class;
    }
}
