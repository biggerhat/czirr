<?php

use App\Models\Recipe;
use App\Models\RecipeTag;

test('admin can create a recipe tag', function () {
    $user = createAdminUser();

    $this->actingAs($user)
        ->postJson('/recipe-tags', ['name' => 'Quick Meals'])
        ->assertCreated()
        ->assertJsonFragment(['name' => 'Quick Meals']);

    $this->assertDatabaseHas('recipe_tags', ['name' => 'Quick Meals', 'user_id' => $user->id]);
});

test('duplicate tag name returns 422', function () {
    $user = createAdminUser();
    RecipeTag::factory()->create(['user_id' => $user->id, 'name' => 'Spicy']);

    $this->actingAs($user)
        ->postJson('/recipe-tags', ['name' => 'Spicy'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.name.0', 'You already have a tag with this name.');
});

test('admin can delete own tag', function () {
    $user = createAdminUser();
    $tag = RecipeTag::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->deleteJson("/recipe-tags/{$tag->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('recipe_tags', ['id' => $tag->id]);
});

test('cannot delete global tag', function () {
    $user = createAdminUser();
    $tag = RecipeTag::factory()->global()->create();

    $this->actingAs($user)
        ->deleteJson("/recipe-tags/{$tag->id}")
        ->assertForbidden();
});

test('deleting tag detaches from recipes', function () {
    $user = createAdminUser();
    $tag = RecipeTag::factory()->create(['user_id' => $user->id]);
    $recipe = Recipe::factory()->create(['user_id' => $user->id]);
    $recipe->tags()->attach($tag->id);

    $this->actingAs($user)->deleteJson("/recipe-tags/{$tag->id}")->assertNoContent();

    expect($recipe->fresh()->tags)->toHaveCount(0);
});

test('child role cannot create tags', function () {
    $user = createUserWithRole('child');

    $this->actingAs($user)
        ->postJson('/recipe-tags', ['name' => 'Vegan'])
        ->assertForbidden();
});
