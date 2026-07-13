<?php

declare(strict_types=1);

namespace Capell\DemoKit\Manifest;

use Capell\Core\Contracts\Extensions\RegistersExtensionFilamentWidget;

final class DemoKitRenderablesContribution implements RegistersExtensionFilamentWidget
{
    public static function compatibleCapellApiVersion(): string
    {
        return '^1.0';
    }
}
