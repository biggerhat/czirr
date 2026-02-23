<?php

use App\Models\Recipe;
use App\Models\RecipeTag;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $this->get(route('recipes.index'))->assertRedirect(route('login'));
});

test('authenticated user can view recipes index', function () {
    $user = createAdminUser();

    $this->actingAs($user)
        ->get(route('recipes.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('recipes/Index'));
});

test('admin can view create recipe page', function () {
    $user = createAdminUser();

    $this->actingAs($user)
        ->get(route('recipes.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('recipes/Create'));
});

test('admin can view a recipe', function () {
    $user = createAdminUser();
    $recipe = Recipe::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('recipes.show', $recipe))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('recipes/Show'));
});

test('admin can view edit recipe page', function () {
    $user = createAdminUser();
    $recipe = Recipe::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('recipes.edit', $recipe))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('recipes/Edit'));
});

test('admin can create a recipe', function () {
    $user = createAdminUser();

    $this->actingAs($user)
        ->postJson('/recipes', [
            'name' => 'Pasta Carbonara',
            'ingredients' => [
                ['name' => 'spaghetti', 'quantity' => '1', 'unit' => 'lbs'],
                ['name' => 'bacon', 'quantity' => '6', 'unit' => 'pieces'],
            ],
            'instructions' => 'Cook the pasta. Fry the bacon. Mix together.',
            'prep_time' => 10,
            'cook_time' => 20,
            'servings' => 4,
            'difficulty' => 'medium',
        ])
        ->assertCreated()
        ->assertJsonFragment(['name' => 'Pasta Carbonara']);

    $this->assertDatabaseHas('recipes', ['name' => 'Pasta Carbonara', 'user_id' => $user->id]);
});

test('admin can create a recipe with tag_ids', function () {
    $user = createAdminUser();
    $tag = RecipeTag::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->postJson('/recipes', [
            'name' => 'Tagged Recipe',
            'ingredients' => [['name' => 'flour', 'quantity' => '2', 'unit' => 'cups']],
            'instructions' => 'Mix and bake.',
            'prep_time' => 5,
            'cook_time' => 30,
            'servings' => 8,
            'difficulty' => 'easy',
            'tag_ids' => [$tag->id],
        ])
        ->assertCreated();

    $recipe = Recipe::where('name', 'Tagged Recipe')->first();
    expect($recipe->tags)->toHaveCount(1);
});

test('admin can update own recipe', function () {
    $user = createAdminUser();
    $recipe = Recipe::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->putJson("/recipes/{$recipe->id}", [
            'name' => 'Updated Recipe',
            'ingredients' => [['name' => 'water', 'quantity' => '1', 'unit' => 'cups']],
            'instructions' => 'Boil water.',
            'prep_time' => 1,
            'cook_time' => 5,
            'servings' => 1,
            'difficulty' => 'easy',
        ])
        ->assertOk()
        ->assertJsonFragment(['name' => 'Updated Recipe']);
});

test('user cannot update another user\'s recipe', function () {
    $user = createAdminUser();
    $other = User::factory()->create();
    $recipe = Recipe::factory()->create(['user_id' => $other->id]);

    $this->actingAs($user)
        ->putJson("/recipes/{$recipe->id}", [
            'name' => 'Hacked',
            'ingredients' => [['name' => 'hack', 'quantity' => '1', 'unit' => 'cups']],
            'instructions' => 'hack',
            'prep_time' => 1,
            'cook_time' => 1,
            'servings' => 1,
            'difficulty' => 'easy',
        ])
        ->assertForbidden();
});

test('admin can delete own recipe', function () {
    $user = createAdminUser();
    $recipe = Recipe::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->deleteJson("/recipes/{$recipe->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('recipes', ['id' => $recipe->id]);
});

test('creating recipe requires ingredients', function () {
    $user = createAdminUser();

    $this->actingAs($user)
        ->postJson('/recipes', [
            'name' => 'No Ingredients',
            'instructions' => 'Do nothing',
            'prep_time' => 0,
            'cook_time' => 0,
            'servings' => 1,
            'difficulty' => 'easy',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('ingredients');
});

test('child role cannot create recipes', function () {
    $user = createUserWithRole('child');

    $this->actingAs($user)
        ->postJson('/recipes', [
            'name' => 'Test',
            'ingredients' => [['name' => 'test', 'quantity' => '1', 'unit' => 'cups']],
            'instructions' => 'test',
            'prep_time' => 1,
            'cook_time' => 1,
            'servings' => 1,
            'difficulty' => 'easy',
        ])
        ->assertForbidden();
});
