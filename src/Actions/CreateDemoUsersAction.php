<?php

declare(strict_types=1);

namespace Capell\DemoKit\Actions;

use BezhanSalleh\FilamentShield\Support\Utils;
use Capell\Core\Models\Site;
use Capell\Core\Support\Permissions\PermissionTeamContext;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Hash;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;
use RuntimeException;
use Spatie\Permission\Models\Role;

/**
 * @method static void run(Site $site)
 */
final class CreateDemoUsersAction
{
    use AsFake;
    use AsObject;

    public function handle(Site $site): void
    {
        $this->assertSafeEnvironment();

        $this->createUser(
            name: 'Demo Admin',
            email: 'demo@example.com',
            password: 'password',
            roleName: Utils::getSuperAdminName(),
            site: $site,
        );

        $this->createUser(
            name: 'Demo Editor',
            email: 'editor@example.com',
            password: 'password',
            roleName: 'editor',
            site: $site,
        );
    }

    /**
     * The demo users are seeded with well-known credentials (including a
     * super-admin at demo@example.com / password). Minting those outside a
     * local or testing environment would hand an attacker a known-credential
     * super-admin, so this is an unconditional, non-overridable safeguard.
     */
    private function assertSafeEnvironment(): void
    {
        if (app()->environment(['local', 'testing'])) {
            return;
        }

        throw new RuntimeException(
            'Refusing to create known-credential demo users outside the local or testing environment.',
        );
    }

    private function createUser(string $name, string $email, string $password, string $roleName, Site $site): void
    {
        /** @var class-string<User> $userModel */
        $userModel = config('auth.providers.users.model');

        /** @var User $user */
        $user = $userModel::query()->where('email', $email)->first() ?? new $userModel;
        $user->email = $email;
        $user->name = $name;
        $user->password = Hash::make($password);
        $user->save();

        $guardName = (string) config('auth.defaults.guard', 'web');

        if ($roleName === Utils::getSuperAdminName()) {
            PermissionTeamContext::run(null, function () use ($user, $roleName, $guardName): void {
                $user->assignRole(Role::findOrCreate($roleName, $guardName));
            }, $user);
        } else {
            $role = PermissionTeamContext::run($site->id, fn (): Role => Role::findOrCreate($roleName, $guardName));
            $user->assignRoleForSite($site, $role);
        }

        if ($roleName !== Utils::getSuperAdminName() && Utils::isPanelUserRoleEnabled()) {
            // Panel access is not a site-content role, so keep it global.
            PermissionTeamContext::run(null, function () use ($user, $guardName): void {
                $user->assignRole(Role::findOrCreate(Utils::getPanelUserRoleName(), $guardName));
            }, $user);
        }
    }
}
