<?php

use App\Models\Cookbook;
use App\Models\Recipe;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $this->get(route('cookbooks.index'))->assertRedirect(route('login'));
});

test('authenticated user can view cookbooks index', function () {
    $user = createAdminUser();

    $this->actingAs($user)
        ->get(route('cookbooks.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('recipes/cookbooks/Index'));
});

test('authenticated user can view a cookbook', function () {
    $user = createAdminUser();
    $cookbook = Cookbook::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('cookbooks.show', $cookbook))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('recipes/cookbooks/Show'));
});

test('admin can create a cookbook', function () {
    $user = createAdminUser();

    $this->actingAs($user)
        ->postJson('/cookbooks', [
            'name' => 'Holiday Recipes',
            'visibility' => 'everyone',
        ])
        ->assertCreated()
        ->assertJsonFragment(['name' => 'Holiday Recipes']);

    $this->assertDatabaseHas('cookbooks', ['name' => 'Holiday Recipes', 'user_id' => $user->id]);
});

test('admin can update own cookbook', function () {
    $user = createAdminUser();
    $cookbook = Cookbook::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->putJson("/cookbooks/{$cookbook->id}", [
            'name' => 'Updated Cookbook',
            'visibility' => 'everyone',
        ])
        ->assertOk()
        ->assertJsonFragment(['name' => 'Updated Cookbook']);
});

test('user cannot update another user\'s cookbook', function () {
    $user = createAdminUser();
    $other = User::factory()->create();
    $cookbook = Cookbook::factory()->create(['user_id' => $other->id]);

    $this->actingAs($user)
        ->putJson("/cookbooks/{$cookbook->id}", [
            'name' => 'Hacked',
            'visibility' => 'everyone',
        ])
        ->assertForbidden();
});

test('admin can delete own cookbook', function () {
    $user = createAdminUser();
    $cookbook = Cookbook::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->deleteJson("/cookbooks/{$cookbook->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('cookbooks', ['id' => $cookbook->id]);
});

test('admin can add recipe to cookbook', function () {
    $user = createAdminUser();
    $cookbook = Cookbook::factory()->create(['user_id' => $user->id]);
    $recipe = Recipe::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->postJson("/cookbooks/{$cookbook->id}/recipes", [
            'recipe_id' => $recipe->id,
        ])
        ->assertCreated();

    expect($cookbook->fresh()->recipes)->toHaveCount(1);
});

test('admin can remove recipe from cookbook', function () {
    $user = createAdminUser();
    $cookbook = Cookbook::factory()->create(['user_id' => $user->id]);
    $recipe = Recipe::factory()->create(['user_id' => $user->id]);
    $cookbook->recipes()->attach($recipe->id, ['position' => 0]);

    $this->actingAs($user)
        ->deleteJson("/cookbooks/{$cookbook->id}/recipes/{$recipe->id}")
        ->assertNoContent();

    expect($cookbook->fresh()->recipes)->toHaveCount(0);
});

test('child role cannot create cookbooks', function () {
    $user = createUserWithRole('child');

    $this->actingAs($user)
        ->postJson('/cookbooks', [
            'name' => 'Test',
            'visibility' => 'everyone',
        ])
        ->assertForbidden();
});
