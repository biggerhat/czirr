<?php

use App\Models\Cuisine;
use App\Models\Recipe;

test('admin can create a cuisine', function () {
    $user = createAdminUser();

    $this->actingAs($user)
        ->postJson('/cuisines', ['name' => 'Thai'])
        ->assertCreated()
        ->assertJsonFragment(['name' => 'Thai']);

    $this->assertDatabaseHas('cuisines', ['name' => 'Thai', 'user_id' => $user->id]);
});

test('duplicate cuisine name returns 422', function () {
    $user = createAdminUser();
    Cuisine::factory()->create(['user_id' => $user->id, 'name' => 'Italian']);

    $this->actingAs($user)
        ->postJson('/cuisines', ['name' => 'Italian'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.name.0', 'You already have a cuisine with this name.');
});

test('admin can delete own cuisine', function () {
    $user = createAdminUser();
    $cuisine = Cuisine::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->deleteJson("/cuisines/{$cuisine->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('cuisines', ['id' => $cuisine->id]);
});

test('cannot delete global cuisine', function () {
    $user = createAdminUser();
    $cuisine = Cuisine::factory()->global()->create();

    $this->actingAs($user)
        ->deleteJson("/cuisines/{$cuisine->id}")
        ->assertForbidden();
});

test('deleting cuisine nullifies recipe cuisine_id', function () {
    $user = createAdminUser();
    $cuisine = Cuisine::factory()->create(['user_id' => $user->id]);
    $recipe = Recipe::factory()->create(['user_id' => $user->id, 'cuisine_id' => $cuisine->id]);

    $this->actingAs($user)->deleteJson("/cuisines/{$cuisine->id}")->assertNoContent();

    expect($recipe->fresh()->cuisine_id)->toBeNull();
});

test('child role cannot create cuisines', function () {
    $user = createUserWithRole('child');

    $this->actingAs($user)
        ->postJson('/cuisines', ['name' => 'French'])
        ->assertForbidden();
});
