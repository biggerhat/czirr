<?php

use App\Models\FamilyList;
use App\Models\FamilyListItem;
use App\Models\User;

test('admin can add item to own list', function () {
    $user = createAdminUser();
    $list = FamilyList::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->postJson("/lists/{$list->id}/items", [
            'name' => 'Milk',
            'quantity' => '1 gallon',
        ])
        ->assertCreated()
        ->assertJsonFragment(['name' => 'Milk']);

    $this->assertDatabaseHas('family_list_items', ['name' => 'Milk', 'family_list_id' => $list->id]);
});

test('admin can update a list item', function () {
    $user = createAdminUser();
    $list = FamilyList::factory()->create(['user_id' => $user->id]);
    $item = FamilyListItem::factory()->create(['family_list_id' => $list->id, 'name' => 'Milk']);

    $this->actingAs($user)
        ->putJson("/list-items/{$item->id}", [
            'name' => 'Almond Milk',
        ])
        ->assertOk()
        ->assertJsonFragment(['name' => 'Almond Milk']);
});

test('admin can toggle item completion', function () {
    $user = createAdminUser();
    $list = FamilyList::factory()->create(['user_id' => $user->id]);
    $item = FamilyListItem::factory()->create(['family_list_id' => $list->id, 'is_completed' => false]);

    $this->actingAs($user)
        ->patchJson("/list-items/{$item->id}/toggle")
        ->assertOk()
        ->assertJsonFragment(['is_completed' => true]);
});

test('admin can clear completed items', function () {
    $user = createAdminUser();
    $list = FamilyList::factory()->create(['user_id' => $user->id]);
    FamilyListItem::factory()->create(['family_list_id' => $list->id, 'is_completed' => true]);
    FamilyListItem::factory()->create(['family_list_id' => $list->id, 'is_completed' => false]);

    $this->actingAs($user)
        ->deleteJson("/lists/{$list->id}/items/completed")
        ->assertNoContent();

    expect(FamilyListItem::where('family_list_id', $list->id)->count())->toBe(1);
});

test('admin can delete a list item', function () {
    $user = createAdminUser();
    $list = FamilyList::factory()->create(['user_id' => $user->id]);
    $item = FamilyListItem::factory()->create(['family_list_id' => $list->id]);

    $this->actingAs($user)
        ->deleteJson("/list-items/{$item->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('family_list_items', ['id' => $item->id]);
});

test('user cannot modify items on another user\'s list', function () {
    $user = createAdminUser();
    $other = User::factory()->create();
    $list = FamilyList::factory()->create(['user_id' => $other->id]);

    $this->actingAs($user)
        ->postJson("/lists/{$list->id}/items", ['name' => 'Hack'])
        ->assertForbidden();
});

test('adding an item requires name', function () {
    $user = createAdminUser();
    $list = FamilyList::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->postJson("/lists/{$list->id}/items", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
});
