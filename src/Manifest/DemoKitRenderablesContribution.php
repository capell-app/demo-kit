<?php

declare(strict_types=1);

namespace Capell\DemoKit\Manifest;

use Capell\Core\Contracts\Extensions\RegistersExtensionWidget;

final class DemoKitRenderablesContribution implements RegistersExtensionWidget
{
    public static function compatibleCapellApiVersion(): string
    {
        return '^4.0';
    }
}
