<?php

declare(strict_types=1);

namespace Capell\DemoKit\Manifest;

use Capell\Core\Contracts\Extensions\ExtensionContribution;

final class DemoKitConfiguratorContribution implements ExtensionContribution
{
    public static function compatibleCapellApiVersion(): string
    {
        return '^4.0';
    }
}
