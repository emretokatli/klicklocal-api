<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\Authorization\AuthorizationService;
use App\Support\Permission;
use App\Support\PlatformRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('PLATFORM_ADMIN_EMAIL', 'admin@klicklocal.test');
        $password = env('PLATFORM_ADMIN_PASSWORD', 'password');

        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Platform Admin',
                'password' => Hash::make($password),
            ],
        );

        $authorization = app(AuthorizationService::class);
        $authorization->clearWorkspaceTeam();

        $role = Role::findByName(PlatformRole::SUPER_ADMIN, Permission::GUARD);
        $user->syncRoles([$role]);
    }
}
