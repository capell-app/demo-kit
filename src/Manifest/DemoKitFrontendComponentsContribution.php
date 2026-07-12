<?php

declare(strict_types=1);

namespace Capell\DemoKit\Manifest;

use Capell\Core\Contracts\Extensions\RegistersExtensionFrontendComponent;

final class DemoKitFrontendComponentsContribution implements RegistersExtensionFrontendComponent
{
    public static function compatibleCapellApiVersion(): string
    {
        return '^4.0';
    }
}
