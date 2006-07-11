<?php

declare(strict_types=1);

namespace Capell\DemoKit\Support;

use Capell\Admin\Filament\Pages\ExtensionsPage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;

final class DemoKitPermissions
{
    public static function canManage(?Authenticatable $actor): bool
    {
        return $actor?->can(ExtensionsPage::MANAGE_PERMISSION) ?? false;
    }

    public static function authorize(?Authenticatable $actor): void
    {
        throw_unless(
            self::canManage($actor),
            AuthorizationException::class,
            __('capell-demo-kit::actions.manage_permission_required'),
        );
    }
}
