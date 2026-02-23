<?php

use App\Models\Chore;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $this->get(route('chores.index'))->assertRedirect(route('login'));
});

test('authenticated user can view chores', function () {
    $user = createAdminUser();

    $this->actingAs($user)
        ->get(route('chores.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('chores/Index'));
});

test('admin can create a chore', function () {
    $user = createAdminUser();

    $this->actingAs($user)
        ->postJson('/chores', [
            'name' => 'Wash dishes',
            'description' => 'After dinner',
            'is_active' => true,
        ])
        ->assertCreated()
        ->assertJsonFragment(['name' => 'Wash dishes']);

    $this->assertDatabaseHas('chores', ['name' => 'Wash dishes', 'user_id' => $user->id]);
});

test('admin can update own chore', function () {
    $user = createAdminUser();
    $chore = Chore::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->putJson("/chores/{$chore->id}", [
            'name' => 'Updated Chore',
            'is_active' => false,
        ])
        ->assertOk()
        ->assertJsonFragment(['name' => 'Updated Chore']);
});

test('user cannot update another user\'s chore', function () {
    $user = createAdminUser();
    $other = User::factory()->create();
    $chore = Chore::factory()->create(['user_id' => $other->id]);

    $this->actingAs($user)
        ->putJson("/chores/{$chore->id}", [
            'name' => 'Hacked',
            'is_active' => true,
        ])
        ->assertForbidden();
});

test('admin can delete own chore', function () {
    $user = createAdminUser();
    $chore = Chore::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->deleteJson("/chores/{$chore->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('chores', ['id' => $chore->id]);
});

test('creating chore requires name', function () {
    $user = createAdminUser();

    $this->actingAs($user)
        ->postJson('/chores', ['is_active' => true])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
});

test('child role cannot create chores', function () {
    $user = createUserWithRole('child');

    $this->actingAs($user)
        ->postJson('/chores', [
            'name' => 'Test',
            'is_active' => true,
        ])
        ->assertForbidden();
});
