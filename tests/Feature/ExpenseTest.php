<?php

use App\Models\BudgetCategory;
use App\Models\Expense;
use App\Models\User;

test('admin can create an expense', function () {
    $user = createAdminUser();
    $category = BudgetCategory::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->postJson('/expenses', [
            'name' => 'Groceries',
            'amount' => 85.50,
            'budget_category_id' => $category->id,
            'date' => '2026-02-15',
        ])
        ->assertCreated()
        ->assertJsonFragment(['name' => 'Groceries']);

    $this->assertDatabaseHas('expenses', ['name' => 'Groceries', 'user_id' => $user->id]);
});

test('admin can delete own expense', function () {
    $user = createAdminUser();
    $category = BudgetCategory::factory()->create(['user_id' => $user->id]);
    $expense = Expense::factory()->create(['user_id' => $user->id, 'budget_category_id' => $category->id]);

    $this->actingAs($user)
        ->deleteJson("/expenses/{$expense->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('expenses', ['id' => $expense->id]);
});

test('user cannot delete another user\'s expense', function () {
    $user = createAdminUser();
    $other = User::factory()->create();
    $category = BudgetCategory::factory()->create(['user_id' => $other->id]);
    $expense = Expense::factory()->create(['user_id' => $other->id, 'budget_category_id' => $category->id]);

    $this->actingAs($user)
        ->deleteJson("/expenses/{$expense->id}")
        ->assertForbidden();
});

test('creating expense requires name', function () {
    $user = createAdminUser();
    $category = BudgetCategory::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->postJson('/expenses', [
            'amount' => 50,
            'budget_category_id' => $category->id,
            'date' => '2026-02-15',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
});

test('child role cannot create expenses', function () {
    $user = createUserWithRole('child');

    $this->actingAs($user)
        ->postJson('/expenses', [
            'name' => 'Test',
            'amount' => 10,
            'budget_category_id' => 1,
            'date' => '2026-02-15',
        ])
        ->assertForbidden();
});
