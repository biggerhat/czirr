<?php

use App\Models\Bill;
use App\Models\BudgetCategory;
use App\Models\Expense;
use App\Models\User;

test('admin can create a budget category', function () {
    $user = createAdminUser();

    $this->actingAs($user)
        ->postJson('/budget-categories', [
            'name' => 'Savings',
            'color' => 'green',
        ])
        ->assertCreated()
        ->assertJsonFragment(['name' => 'Savings']);

    $this->assertDatabaseHas('budget_categories', ['name' => 'Savings', 'user_id' => $user->id]);
});

test('admin can update own category', function () {
    $user = createAdminUser();
    $category = BudgetCategory::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->putJson("/budget-categories/{$category->id}", [
            'name' => 'Updated Category',
            'color' => 'red',
        ])
        ->assertOk()
        ->assertJsonFragment(['name' => 'Updated Category']);
});

test('user cannot update another user\'s category', function () {
    $user = createAdminUser();
    $other = User::factory()->create();
    $category = BudgetCategory::factory()->create(['user_id' => $other->id]);

    $this->actingAs($user)
        ->putJson("/budget-categories/{$category->id}", [
            'name' => 'Hacked',
            'color' => 'red',
        ])
        ->assertForbidden();
});

test('admin can delete empty category', function () {
    $user = createAdminUser();
    $category = BudgetCategory::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->deleteJson("/budget-categories/{$category->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('budget_categories', ['id' => $category->id]);
});

test('cannot delete category with bills', function () {
    $user = createAdminUser();
    $category = BudgetCategory::factory()->create(['user_id' => $user->id]);
    Bill::factory()->create(['user_id' => $user->id, 'budget_category_id' => $category->id]);

    $this->actingAs($user)
        ->deleteJson("/budget-categories/{$category->id}")
        ->assertUnprocessable()
        ->assertJsonFragment(['message' => 'Cannot delete category with existing bills or expenses.']);
});

test('cannot delete category with expenses', function () {
    $user = createAdminUser();
    $category = BudgetCategory::factory()->create(['user_id' => $user->id]);
    Expense::factory()->create(['user_id' => $user->id, 'budget_category_id' => $category->id]);

    $this->actingAs($user)
        ->deleteJson("/budget-categories/{$category->id}")
        ->assertUnprocessable();
});

test('creating category requires name', function () {
    $user = createAdminUser();

    $this->actingAs($user)
        ->postJson('/budget-categories', ['color' => 'red'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
});

test('child role cannot create categories', function () {
    $user = createUserWithRole('child');

    $this->actingAs($user)
        ->postJson('/budget-categories', ['name' => 'Test', 'color' => 'red'])
        ->assertForbidden();
});
