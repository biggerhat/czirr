<?php

use App\Models\MealPlanEntry;
use App\Models\Recipe;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $this->get(route('meal-plans.index'))->assertRedirect(route('login'));
});

test('authenticated user can view meal plans', function () {
    $user = createAdminUser();

    $this->actingAs($user)
        ->get(route('meal-plans.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('meal-plans/Index'));
});

test('admin can create a meal plan entry', function () {
    $user = createAdminUser();

    $this->actingAs($user)
        ->postJson('/meal-plan-entries', [
            'date' => '2026-03-01',
            'meal_type' => 'dinner',
            'name' => 'Pasta Night',
        ])
        ->assertCreated()
        ->assertJsonFragment(['name' => 'Pasta Night']);

    $this->assertDatabaseHas('meal_plan_entries', ['name' => 'Pasta Night', 'user_id' => $user->id]);
});

test('admin can create entry with recipe_id', function () {
    $user = createAdminUser();
    $recipe = Recipe::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->postJson('/meal-plan-entries', [
            'date' => '2026-03-01',
            'meal_type' => 'dinner',
            'name' => $recipe->name,
            'recipe_id' => $recipe->id,
        ])
        ->assertCreated();

    $this->assertDatabaseHas('meal_plan_entries', ['recipe_id' => $recipe->id]);
});

test('admin can update own entry', function () {
    $user = createAdminUser();
    $entry = MealPlanEntry::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->putJson("/meal-plan-entries/{$entry->id}", [
            'date' => '2026-03-02',
            'meal_type' => 'breakfast',
            'name' => 'Updated Meal',
        ])
        ->assertOk()
        ->assertJsonFragment(['name' => 'Updated Meal']);
});

test('user cannot update another user\'s entry', function () {
    $user = createAdminUser();
    $other = User::factory()->create();
    $entry = MealPlanEntry::factory()->create(['user_id' => $other->id]);

    $this->actingAs($user)
        ->putJson("/meal-plan-entries/{$entry->id}", [
            'date' => '2026-03-02',
            'meal_type' => 'breakfast',
            'name' => 'Hacked',
        ])
        ->assertForbidden();
});

test('admin can delete own entry', function () {
    $user = createAdminUser();
    $entry = MealPlanEntry::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->deleteJson("/meal-plan-entries/{$entry->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('meal_plan_entries', ['id' => $entry->id]);
});

test('generate grocery list creates a family list', function () {
    $user = createAdminUser();
    $recipe = Recipe::factory()->create([
        'user_id' => $user->id,
        'ingredients' => [
            ['name' => 'chicken', 'quantity' => '2', 'unit' => 'lbs'],
            ['name' => 'rice', 'quantity' => '1', 'unit' => 'cups'],
        ],
    ]);
    MealPlanEntry::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-03-01',
        'meal_type' => 'dinner',
        'recipe_id' => $recipe->id,
        'name' => $recipe->name,
    ]);

    $this->actingAs($user)
        ->postJson('/meal-plans/generate-grocery-list', [
            'start' => '2026-03-01',
            'end' => '2026-03-07',
        ])
        ->assertCreated()
        ->assertJsonStructure(['id']);
});

test('generate grocery list with no ingredients returns 422', function () {
    $user = createAdminUser();
    // No entries with recipes exist
    MealPlanEntry::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-03-01',
        'meal_type' => 'dinner',
        'recipe_id' => null,
        'name' => 'Takeout',
    ]);

    $this->actingAs($user)
        ->postJson('/meal-plans/generate-grocery-list', [
            'start' => '2026-03-01',
            'end' => '2026-03-07',
        ])
        ->assertUnprocessable();
});

test('generate grocery list conflict returns 409', function () {
    $user = createAdminUser();
    $recipe = Recipe::factory()->create([
        'user_id' => $user->id,
        'ingredients' => [['name' => 'flour', 'quantity' => '1', 'unit' => 'cups']],
    ]);
    MealPlanEntry::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-03-01',
        'meal_type' => 'dinner',
        'recipe_id' => $recipe->id,
        'name' => $recipe->name,
    ]);

    // First call creates the list
    $this->actingAs($user)
        ->postJson('/meal-plans/generate-grocery-list', [
            'start' => '2026-03-01',
            'end' => '2026-03-07',
        ])
        ->assertCreated();

    // Second call with same range → conflict
    $this->actingAs($user)
        ->postJson('/meal-plans/generate-grocery-list', [
            'start' => '2026-03-01',
            'end' => '2026-03-07',
        ])
        ->assertConflict();
});

test('generate grocery list mode=replace returns 200', function () {
    $user = createAdminUser();
    $recipe = Recipe::factory()->create([
        'user_id' => $user->id,
        'ingredients' => [['name' => 'flour', 'quantity' => '1', 'unit' => 'cups']],
    ]);
    MealPlanEntry::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-03-01',
        'meal_type' => 'dinner',
        'recipe_id' => $recipe->id,
        'name' => $recipe->name,
    ]);

    // First call creates
    $this->actingAs($user)
        ->postJson('/meal-plans/generate-grocery-list', [
            'start' => '2026-03-01',
            'end' => '2026-03-07',
        ])
        ->assertCreated();

    // Replace mode
    $this->actingAs($user)
        ->postJson('/meal-plans/generate-grocery-list', [
            'start' => '2026-03-01',
            'end' => '2026-03-07',
            'mode' => 'replace',
        ])
        ->assertOk();
});

test('child role cannot create meal plan entries', function () {
    $user = createUserWithRole('child');

    $this->actingAs($user)
        ->postJson('/meal-plan-entries', [
            'date' => '2026-03-01',
            'meal_type' => 'dinner',
            'name' => 'Test',
        ])
        ->assertForbidden();
});
