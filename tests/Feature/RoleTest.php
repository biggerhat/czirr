<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

test('admin can list roles', function () {
    $user = createAdminUser();

    $this->actingAs($user)
        ->getJson(route('roles.index'))
        ->assertOk()
        ->assertJsonStructure(['roles', 'availablePermissions']);
});

test('admin can create custom role', function () {
    $user = createAdminUser();

    $this->actingAs($user)
        ->postJson(route('roles.store'), [
            'name' => 'custom-role',
            'permissions' => ['events.view', 'lists.view'],
        ])
        ->assertCreated()
        ->assertJsonFragment(['name' => 'custom-role']);

    $this->assertDatabaseHas('roles', ['name' => 'custom-role']);
});

test('admin can update role permissions', function () {
    $user = createAdminUser();
    $role = Role::create(['name' => 'test-role']);
    $role->syncPermissions(['events.view']);

    $this->actingAs($user)
        ->putJson(route('roles.update', $role), [
            'permissions' => ['events.view', 'events.create'],
        ])
        ->assertOk();

    expect($role->fresh()->permissions->pluck('name')->toArray())
        ->toContain('events.create');
});

test('default roles cannot be renamed', function () {
    $user = createAdminUser();
    $role = Role::where('name', 'admin')->first();

    $this->actingAs($user)
        ->putJson(route('roles.update', $role), [
            'name' => 'renamed-admin',
            'permissions' => ['events.view'],
        ])
        ->assertOk();

    // Name should NOT have changed
    expect($role->fresh()->name)->toBe('admin');
});

test('admin can delete custom role', function () {
    $user = createAdminUser();
    $role = Role::create(['name' => 'deleteable-role']);
    $role->syncPermissions(['events.view']);

    $this->actingAs($user)
        ->deleteJson(route('roles.destroy', $role))
        ->assertNoContent();

    $this->assertDatabaseMissing('roles', ['name' => 'deleteable-role']);
});

test('cannot delete default role', function () {
    $user = createAdminUser();
    $role = Role::where('name', 'admin')->first();

    $this->actingAs($user)
        ->deleteJson(route('roles.destroy', $role))
        ->assertUnprocessable()
        ->assertJsonFragment(['message' => 'Default roles cannot be deleted.']);
});

test('cannot delete role with users', function () {
    $user = createAdminUser();
    $role = Role::create(['name' => 'used-role']);
    $role->syncPermissions(['events.view']);
    User::factory()->create()->assignRole('used-role');

    $this->actingAs($user)
        ->deleteJson(route('roles.destroy', $role))
        ->assertUnprocessable()
        ->assertJsonFragment(['message' => 'Cannot delete a role that has users assigned.']);
});

test('creating role requires name', function () {
    $user = createAdminUser();

    $this->actingAs($user)
        ->postJson(route('roles.store'), [
            'permissions' => ['events.view'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
});

test('parent role cannot manage roles', function () {
    $user = createUserWithRole('parent');

    $this->actingAs($user)
        ->getJson(route('roles.index'))
        ->assertForbidden();
});
