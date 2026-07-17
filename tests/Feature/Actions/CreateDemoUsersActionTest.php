<?php

declare(strict_types=1);

use Capell\DemoKit\Actions\CreateDemoUsersAction;
use Capell\Tests\Fixtures\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

it('refuses to mint known-credential demo users in production', function (): void {
    $originalEnvironment = app()->make('env');
    app()->detectEnvironment(static fn (): string => 'production');

    try {
        expect(function (): void {
            CreateDemoUsersAction::run();
        })
            ->toThrow(
                RuntimeException::class,
                'Refusing to create known-credential demo users outside the local or testing environment.',
            );

        expect(User::query()->where('email', 'demo@example.com')->exists())->toBeFalse()
            ->and(User::query()->where('email', 'editor@example.com')->exists())->toBeFalse();
    } finally {
        app()->detectEnvironment(static fn (): string => is_string($originalEnvironment) ? $originalEnvironment : 'testing');
    }
});

it('assigns panel access to the demo editor', function (): void {
    config()->set('filament-shield.panel_user.enabled', true);
    config()->set('filament-shield.panel_user.name', 'panel_user');

    CreateDemoUsersAction::run();

    /** @var User $editor */
    $editor = User::query()->where('email', 'editor@example.com')->firstOrFail();

    expect(Role::query()->where('name', 'panel_user')->exists())->toBeTrue()
        ->and(userHasRole($editor, 'editor'))->toBeTrue()
        ->and(userHasRole($editor, 'panel_user'))->toBeTrue();
});

function userHasRole(User $user, string $roleName): bool
{
    return DB::table('model_has_roles')
        ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
        ->where('roles.name', $roleName)
        ->where('model_has_roles.model_type', $user->getMorphClass())
        ->where('model_has_roles.model_id', $user->getKey())
        ->exists();
}
