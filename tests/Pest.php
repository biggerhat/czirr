<?php

use App\Enums\FamilyRole;
use App\Models\FamilyMember;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

function createAdminUser(): User
{
    (new RoleAndPermissionSeeder)->run();

    $user = User::factory()->create();
    $user->assignRole('admin');

    FamilyMember::factory()->create([
        'user_id' => $user->id,
        'name' => $user->name,
        'role' => FamilyRole::Parent,
        'linked_user_id' => $user->id,
    ]);

    return $user;
}

function createUserWithRole(string $roleName): User
{
    (new RoleAndPermissionSeeder)->run();

    $admin = User::factory()->create();
    FamilyMember::factory()->create([
        'user_id' => $admin->id,
        'name' => $admin->name,
        'role' => FamilyRole::Parent,
        'linked_user_id' => $admin->id,
    ]);

    $user = User::factory()->create();
    $user->assignRole($roleName);

    FamilyMember::factory()->create([
        'user_id' => $admin->id,
        'name' => $user->name,
        'role' => FamilyRole::Child,
        'linked_user_id' => $user->id,
    ]);

    return $user;
}
