<?php

use App\Models\FamilyList;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $this->get(route('lists.index'))->assertRedirect(route('login'));
});

test('authenticated user can view lists index', function () {
    $user = createAdminUser();

    $this->actingAs($user)
        ->get(route('lists.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('lists/Index'));
});

test('authenticated user can view a list', function () {
    $user = createAdminUser();
    $list = FamilyList::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('lists.show', $list))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('lists/Show'));
});

test('admin can create a list', function () {
    $user = createAdminUser();

    $this->actingAs($user)
        ->postJson('/lists', [
            'name' => 'Groceries',
            'type' => 'grocery',
            'visibility' => 'everyone',
        ])
        ->assertCreated()
        ->assertJsonFragment(['name' => 'Groceries']);

    $this->assertDatabaseHas('family_lists', ['name' => 'Groceries', 'user_id' => $user->id]);
});

test('admin can create a list with specific visibility and member_ids', function () {
    $user = createAdminUser();
    $member = \App\Models\FamilyMember::where('user_id', $user->id)->first();

    $this->actingAs($user)
        ->postJson('/lists', [
            'name' => 'Private List',
            'type' => 'todo',
            'visibility' => 'specific',
            'member_ids' => [$member->id],
        ])
        ->assertCreated();

    $list = FamilyList::where('name', 'Private List')->first();
    expect($list->members)->toHaveCount(1);
});

test('admin can update own list', function () {
    $user = createAdminUser();
    $list = FamilyList::factory()->create(['user_id' => $user->id, 'type' => 'grocery', 'visibility' => 'everyone']);

    $this->actingAs($user)
        ->putJson("/lists/{$list->id}", [
            'name' => 'Updated List',
            'type' => 'grocery',
            'visibility' => 'everyone',
        ])
        ->assertOk()
        ->assertJsonFragment(['name' => 'Updated List']);
});

test('user cannot update another user\'s list', function () {
    $user = createAdminUser();
    $other = User::factory()->create();
    $list = FamilyList::factory()->create(['user_id' => $other->id, 'type' => 'grocery', 'visibility' => 'everyone']);

    $this->actingAs($user)
        ->putJson("/lists/{$list->id}", [
            'name' => 'Hacked',
            'type' => 'grocery',
            'visibility' => 'everyone',
        ])
        ->assertForbidden();
});

test('admin can delete own list', function () {
    $user = createAdminUser();
    $list = FamilyList::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->deleteJson("/lists/{$list->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('family_lists', ['id' => $list->id]);
});

test('creating a list requires name', function () {
    $user = createAdminUser();

    $this->actingAs($user)
        ->postJson('/lists', [
            'type' => 'grocery',
            'visibility' => 'everyone',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
});
