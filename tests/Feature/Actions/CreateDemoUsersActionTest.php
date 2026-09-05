<?php

declare(strict_types=1);

use Capell\Core\Models\Site;
use Capell\DemoKit\Actions\CreateDemoUsersAction;
use Capell\Tests\Fixtures\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

it('refuses to mint known-credential demo users in production', function (): void {
    $originalEnvironment = app()->make('env');
    app()->detectEnvironment(static fn (): string => 'production');

    try {
        expect(function (): void {
            CreateDemoUsersAction::run(new Site);
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

    $site = Site::factory()->create();

    CreateDemoUsersAction::run($site);

    /** @var User $editor */
    $editor = User::query()->where('email', 'editor@example.com')->firstOrFail();

    expect(Role::query()->where('name', 'panel_user')->exists())->toBeTrue()
        ->and(userHasRole($editor, 'editor'))->toBeTrue()
        ->and(userHasRole($editor, 'panel_user'))->toBeTrue();
});

// config('permission.teams') is false in production today (CAP-0532's cutover
// hasn't happened yet), so Spatie's own assignRole() writes team_id=NULL for
// every assignment regardless of which helper called it -- PermissionRegistrar
// only writes the pivot's team_id when its own ->teams flag is on, a separate
// thing from setPermissionsTeamId() naming which team. Proving this action
// calls the correct helper (assignRoleForSite() for the editor role, bare
// assignRole() for super_admin/panel-access) therefore means enabling teams
// mode for the scope of this describe block, the same way PagePolicyTest does
// for its own CAP-0532 site-scoping proof.
describe('with permission.teams enabled (post-cutover simulation)', function (): void {
    beforeEach(function (): void {
        config(['permission.teams' => true]);
        resolve(PermissionRegistrar::class)->teams = true;
        resolve(PermissionRegistrar::class)->forgetCachedPermissions();
    });

    afterEach(function (): void {
        resolve(PermissionRegistrar::class)->setPermissionsTeamId(null);
        resolve(PermissionRegistrar::class)->teams = false;
        resolve(PermissionRegistrar::class)->forgetCachedPermissions();
        config(['permission.teams' => false]);
    });

    it('scopes the editor role to its site, and keeps super_admin and panel access global', function (): void {
        config()->set('filament-shield.panel_user.enabled', true);
        config()->set('filament-shield.panel_user.name', 'panel_user');

        $site = Site::factory()->create();

        CreateDemoUsersAction::run($site);

        /** @var User $editor */
        $editor = User::query()->where('email', 'editor@example.com')->firstOrFail();
        /** @var User $admin */
        $admin = User::query()->where('email', 'demo@example.com')->firstOrFail();

        expect(userRoleTeamId($editor, 'editor'))->toBe($site->getKey())
            ->and(userRoleTeamId($editor, 'panel_user'))->toBeNull()
            ->and(userRoleTeamId($admin, 'super_admin'))->toBeNull();
    });
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

function userRoleTeamId(User $user, string $roleName): mixed
{
    return DB::table('model_has_roles')
        ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
        ->where('roles.name', $roleName)
        ->where('model_has_roles.model_type', $user->getMorphClass())
        ->where('model_has_roles.model_id', $user->getKey())
        ->value('model_has_roles.team_id');
}
