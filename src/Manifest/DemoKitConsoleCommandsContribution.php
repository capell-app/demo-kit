<?php

declare(strict_types=1);

namespace Capell\DemoKit\Manifest;

use Capell\Core\Contracts\Extensions\ExtensionContribution;

final class DemoKitConsoleCommandsContribution implements ExtensionContribution
{
    public static function compatibleCapellApiVersion(): string
    {
        return '^0.0';
    }
}
