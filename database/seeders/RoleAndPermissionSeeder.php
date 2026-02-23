<?php

namespace Database\Seeders;

use App\Enums\DefaultRole;
use App\Enums\Permission;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Create all permissions
        foreach (Permission::values() as $permission) {
            \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $permission]);
        }

        // Create default roles and sync permissions
        foreach (DefaultRole::cases() as $defaultRole) {
            $role = Role::firstOrCreate(['name' => $defaultRole->value]);
            $role->syncPermissions($defaultRole->permissions());
        }
    }
}
