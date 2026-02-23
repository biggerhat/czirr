<?php

use App\Models\Event;
use App\Models\Income;
use App\Models\User;

test('admin can create an income', function () {
    $user = createAdminUser();

    $this->actingAs($user)
        ->postJson('/incomes', [
            'name' => 'Salary',
            'amount' => 5000.00,
            'start_date' => '2026-01-15',
            'frequency' => 'monthly',
            'is_active' => true,
        ])
        ->assertCreated()
        ->assertJsonFragment(['name' => 'Salary']);

    $this->assertDatabaseHas('incomes', ['name' => 'Salary', 'user_id' => $user->id]);
});

test('active income creates calendar event', function () {
    $user = createAdminUser();

    $this->actingAs($user)->postJson('/incomes', [
        'name' => 'Paycheck',
        'amount' => 3000.00,
        'start_date' => '2026-01-15',
        'frequency' => 'monthly',
        'is_active' => true,
    ])->assertCreated();

    $income = Income::where('name', 'Paycheck')->first();
    expect($income->event_id)->not->toBeNull();
    expect(Event::find($income->event_id)->source)->toBe('income');
});

test('deactivating income removes event', function () {
    $user = createAdminUser();

    $this->actingAs($user)->postJson('/incomes', [
        'name' => 'Freelance',
        'amount' => 500.00,
        'start_date' => '2026-01-01',
        'frequency' => 'monthly',
        'is_active' => true,
    ]);

    $income = Income::where('name', 'Freelance')->first();
    $eventId = $income->event_id;

    $this->actingAs($user)->putJson("/incomes/{$income->id}", [
        'name' => 'Freelance',
        'amount' => 500.00,
        'start_date' => '2026-01-01',
        'frequency' => 'monthly',
        'is_active' => false,
    ])->assertOk();

    $this->assertDatabaseMissing('events', ['id' => $eventId]);
});

test('user cannot update another user\'s income', function () {
    $user = createAdminUser();
    $other = User::factory()->create();
    $income = Income::factory()->create(['user_id' => $other->id]);

    $this->actingAs($user)
        ->putJson("/incomes/{$income->id}", [
            'name' => 'Hacked',
            'amount' => 0,
            'start_date' => '2026-01-01',
            'frequency' => 'monthly',
        ])
        ->assertForbidden();
});

test('deleting income cleans up event', function () {
    $user = createAdminUser();

    $this->actingAs($user)->postJson('/incomes', [
        'name' => 'Side Gig',
        'amount' => 200.00,
        'start_date' => '2026-01-01',
        'frequency' => 'monthly',
        'is_active' => true,
    ]);

    $income = Income::where('name', 'Side Gig')->first();
    $eventId = $income->event_id;

    $this->actingAs($user)->deleteJson("/incomes/{$income->id}")->assertNoContent();

    $this->assertDatabaseMissing('incomes', ['id' => $income->id]);
    $this->assertDatabaseMissing('events', ['id' => $eventId]);
});

test('creating income requires name', function () {
    $user = createAdminUser();

    $this->actingAs($user)
        ->postJson('/incomes', [
            'amount' => 1000,
            'start_date' => '2026-01-01',
            'frequency' => 'monthly',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
});

test('child role cannot create incomes', function () {
    $user = createUserWithRole('child');

    $this->actingAs($user)
        ->postJson('/incomes', [
            'name' => 'Test',
            'amount' => 10,
            'start_date' => '2026-01-01',
            'frequency' => 'monthly',
        ])
        ->assertForbidden();
});
