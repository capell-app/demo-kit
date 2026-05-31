<?php

declare(strict_types=1);

namespace Capell\DemoKit\Actions;

use BezhanSalleh\FilamentShield\Support\Utils;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Hash;
use Lorisleiva\Actions\Concerns\AsObject;
use Spatie\Permission\Models\Role;

/**
 * @method static void run()
 */
final class CreateDemoUsersAction
{
    use AsObject;

    public function handle(): void
    {
        $this->createUser(
            name: 'Demo Admin',
            email: 'demo@example.com',
            password: 'password',
            roleName: Utils::getSuperAdminName(),
        );

        $this->createUser(
            name: 'Demo Editor',
            email: 'editor@example.com',
            password: 'password',
            roleName: 'editor',
        );
    }

    private function createUser(string $name, string $email, string $password, string $roleName): void
    {
        /** @var class-string<User> $userModel */
        $userModel = config('auth.providers.users.model');

        $roles = [$roleName];

        if ($roleName !== Utils::getSuperAdminName() && Utils::isPanelUserRoleEnabled()) {
            $roles[] = Utils::getPanelUserRoleName();
        }

        /** @var User $user */
        $user = $userModel::query()->where('email', $email)->first() ?? new $userModel;
        $user->email = $email;
        $user->name = $name;
        $user->password = Hash::make($password);
        $user->save();

        $guardName = (string) config('auth.defaults.guard', 'web');

        $user->assignRole(array_map(
            static fn (string $role): Role => Role::findOrCreate($role, $guardName),
            array_values(array_unique($roles)),
        ));
    }
}
