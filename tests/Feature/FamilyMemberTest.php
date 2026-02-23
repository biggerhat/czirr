<?php

use App\Models\FamilyMember;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $this->get(route('family.index'))->assertRedirect(route('login'));
});

test('authenticated user can view family members', function () {
    $user = createAdminUser();

    $this->actingAs($user)
        ->get(route('family.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('family/Index'));
});

test('admin can create a family member', function () {
    $user = createAdminUser();

    $this->actingAs($user)
        ->postJson('/family', [
            'name' => 'Sam',
            'role' => 'child',
            'color' => 'amber',
        ])
        ->assertCreated()
        ->assertJsonFragment(['name' => 'Sam']);

    $this->assertDatabaseHas('family_members', ['name' => 'Sam', 'user_id' => $user->id]);
});

test('admin can create linked family member', function () {
    $user = createAdminUser();
    $linked = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/family', [
            'name' => $linked->name,
            'role' => 'child',
            'color' => 'emerald',
            'linked_user_id' => $linked->id,
        ])
        ->assertCreated();

    // Linked user should have been assigned a spatie role
    expect($linked->fresh()->hasRole('child'))->toBeTrue();
});

test('admin can update family member', function () {
    $user = createAdminUser();
    $member = FamilyMember::factory()->create(['user_id' => $user->id, 'color' => 'blue']);

    $this->actingAs($user)
        ->putJson("/family/{$member->id}", [
            'name' => 'Updated Name',
            'role' => 'child',
            'color' => 'rose',
        ])
        ->assertOk()
        ->assertJsonFragment(['name' => 'Updated Name']);
});

test('user cannot update another user\'s member', function () {
    $user = createAdminUser();
    $other = User::factory()->create();
    $member = FamilyMember::factory()->create(['user_id' => $other->id]);

    $this->actingAs($user)
        ->putJson("/family/{$member->id}", [
            'name' => 'Hacked',
            'role' => 'child',
            'color' => 'amber',
        ])
        ->assertForbidden();
});

test('admin can delete unlinked member', function () {
    $user = createAdminUser();
    $member = FamilyMember::factory()->create(['user_id' => $user->id, 'linked_user_id' => null]);

    $this->actingAs($user)
        ->deleteJson("/family/{$member->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('family_members', ['id' => $member->id]);
});

test('cannot delete own family member entry', function () {
    $user = createAdminUser();
    $selfMember = FamilyMember::where('user_id', $user->id)->where('linked_user_id', $user->id)->first();

    $this->actingAs($user)
        ->deleteJson("/family/{$selfMember->id}")
        ->assertUnprocessable()
        ->assertJsonFragment(['message' => 'You cannot remove your own family member entry.']);
});

test('admin can update spatie role of linked member', function () {
    $user = createAdminUser();
    $linked = User::factory()->create();
    $linked->assignRole('child');
    $member = FamilyMember::factory()->create([
        'user_id' => $user->id,
        'linked_user_id' => $linked->id,
    ]);

    $this->actingAs($user)
        ->putJson("/family/{$member->id}/role", ['role' => 'parent'])
        ->assertOk()
        ->assertJsonFragment(['spatie_role' => 'parent']);

    expect($linked->fresh()->hasRole('parent'))->toBeTrue();
});

test('cannot update role for unlinked member', function () {
    $user = createAdminUser();
    $member = FamilyMember::factory()->create(['user_id' => $user->id, 'linked_user_id' => null]);

    $this->actingAs($user)
        ->putJson("/family/{$member->id}/role", ['role' => 'parent'])
        ->assertUnprocessable();
});

test('creating member requires name', function () {
    $user = createAdminUser();

    $this->actingAs($user)
        ->postJson('/family', ['role' => 'child', 'color' => 'amber'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
});

test('child role cannot create family members', function () {
    $user = createUserWithRole('child');

    $this->actingAs($user)
        ->postJson('/family', ['name' => 'Test', 'role' => 'child', 'color' => 'amber'])
        ->assertForbidden();
});
