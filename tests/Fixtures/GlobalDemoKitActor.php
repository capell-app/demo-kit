<?php

declare(strict_types=1);

namespace Capell\DemoKit\Tests\Fixtures;

use Capell\Tests\Fixtures\Models\User;
use Filament\Panel;
use Override;

final class GlobalDemoKitActor extends User
{
    protected $table = 'users';

    #[Override]
    public function isGlobalAdmin(): bool
    {
        return true;
    }

    #[Override]
    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }
}
