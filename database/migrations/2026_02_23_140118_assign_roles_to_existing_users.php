<?php

use App\Enums\DefaultRole;
use App\Enums\Permission;
use App\Models\FamilyMember;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        // Ensure permissions and roles exist
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (Permission::values() as $permission) {
            \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $permission]);
        }

        foreach (DefaultRole::cases() as $defaultRole) {
            $role = Role::firstOrCreate(['name' => $defaultRole->value]);
            $role->syncPermissions($defaultRole->permissions());
        }

        // Family owners (users who have family_members with user_id = their id) → admin
        $ownerIds = FamilyMember::select('user_id')->distinct()->pluck('user_id');
        User::whereIn('id', $ownerIds)->each(function (User $user) {
            if (! $user->hasAnyRole(DefaultRole::names())) {
                $user->assignRole('admin');
            }
        });

        // Linked users → role based on FamilyMember.role
        FamilyMember::whereNotNull('linked_user_id')
            ->whereColumn('linked_user_id', '!=', 'user_id')
            ->get()
            ->each(function (FamilyMember $member) {
                $user = User::find($member->linked_user_id);
                if ($user && ! $user->hasAnyRole(DefaultRole::names())) {
                    $spatieRole = $member->role?->value === 'child' ? 'child' : 'parent';
                    $user->assignRole($spatieRole);
                }
            });
    }

    public function down(): void
    {
        // Remove all role assignments
        User::all()->each(function (User $user) {
            $user->syncRoles([]);
        });
    }
};
