<?php

use App\Models\Bill;
use App\Models\BudgetCategory;
use App\Models\Event;
use App\Models\User;

test('admin can create a bill', function () {
    $user = createAdminUser();
    $category = BudgetCategory::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->postJson('/bills', [
            'name' => 'Internet',
            'amount' => 65.00,
            'budget_category_id' => $category->id,
            'start_date' => '2026-01-15',
            'frequency' => 'monthly',
            'is_active' => true,
        ])
        ->assertCreated()
        ->assertJsonFragment(['name' => 'Internet']);

    $this->assertDatabaseHas('bills', ['name' => 'Internet', 'user_id' => $user->id]);
});

test('active bill creates calendar event', function () {
    $user = createAdminUser();
    $category = BudgetCategory::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->postJson('/bills', [
            'name' => 'Rent',
            'amount' => 1500.00,
            'budget_category_id' => $category->id,
            'start_date' => '2026-01-01',
            'frequency' => 'monthly',
            'is_active' => true,
        ])
        ->assertCreated();

    $bill = Bill::where('name', 'Rent')->first();
    expect($bill->event_id)->not->toBeNull();
    expect(Event::find($bill->event_id)->source)->toBe('bill');
});

test('deactivating bill removes event', function () {
    $user = createAdminUser();
    $category = BudgetCategory::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->postJson('/bills', [
        'name' => 'Electric',
        'amount' => 100.00,
        'budget_category_id' => $category->id,
        'start_date' => '2026-01-01',
        'frequency' => 'monthly',
        'is_active' => true,
    ]);

    $bill = Bill::where('name', 'Electric')->first();
    $eventId = $bill->event_id;

    $this->actingAs($user)->putJson("/bills/{$bill->id}", [
        'name' => 'Electric',
        'amount' => 100.00,
        'budget_category_id' => $category->id,
        'start_date' => '2026-01-01',
        'frequency' => 'monthly',
        'is_active' => false,
    ])->assertOk();

    $this->assertDatabaseMissing('events', ['id' => $eventId]);
});

test('user cannot update another user\'s bill', function () {
    $user = createAdminUser();
    $other = User::factory()->create();
    $category = BudgetCategory::factory()->create(['user_id' => $other->id]);
    $bill = Bill::factory()->create(['user_id' => $other->id, 'budget_category_id' => $category->id]);

    $this->actingAs($user)
        ->putJson("/bills/{$bill->id}", [
            'name' => 'Hacked',
            'amount' => 0,
            'budget_category_id' => $category->id,
            'start_date' => '2026-01-01',
            'frequency' => 'monthly',
        ])
        ->assertForbidden();
});

test('deleting bill cleans up event', function () {
    $user = createAdminUser();
    $category = BudgetCategory::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->postJson('/bills', [
        'name' => 'Water',
        'amount' => 50.00,
        'budget_category_id' => $category->id,
        'start_date' => '2026-01-01',
        'frequency' => 'monthly',
        'is_active' => true,
    ]);

    $bill = Bill::where('name', 'Water')->first();
    $eventId = $bill->event_id;

    $this->actingAs($user)->deleteJson("/bills/{$bill->id}")->assertNoContent();

    $this->assertDatabaseMissing('bills', ['id' => $bill->id]);
    $this->assertDatabaseMissing('events', ['id' => $eventId]);
});

test('creating bill requires name', function () {
    $user = createAdminUser();
    $category = BudgetCategory::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->postJson('/bills', [
            'amount' => 65,
            'budget_category_id' => $category->id,
            'start_date' => '2026-01-01',
            'frequency' => 'monthly',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
});

test('child role cannot create bills', function () {
    $user = createUserWithRole('child');

    $this->actingAs($user)
        ->postJson('/bills', ['name' => 'Test', 'amount' => 10, 'budget_category_id' => 1, 'start_date' => '2026-01-01', 'frequency' => 'monthly'])
        ->assertForbidden();
});
