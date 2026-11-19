<?php

use App\Models\Bill;
use App\Models\BudgetCategory;
use App\Models\Cookbook;
use App\Models\Recipe;
use App\Models\User;

function foreignFamily(): User
{
    $u = User::factory()->create();
    $u->familyMembers()->create(['name' => 'Other', 'role' => 'parent', 'color' => 'blue', 'linked_user_id' => $u->id]);
    $u->assignRole('parent');

    return $u;
}

test('expenses cannot reference another family\'s bill or category', function () {
    $me = createAdminUser();
    $them = foreignFamily();
    $mine = BudgetCategory::factory()->create(['user_id' => $me->id]);
    $theirCat = BudgetCategory::factory()->create(['user_id' => $them->id]);
    $theirBill = Bill::factory()->create(['user_id' => $them->id]);
    $base = ['name' => 'x', 'amount' => 1, 'date' => now()->toDateString()];

    $this->actingAs($me)->postJson('/expenses', [...$base, 'budget_category_id' => $theirCat->id])->assertUnprocessable();
    $this->actingAs($me)->postJson('/expenses', [...$base, 'budget_category_id' => $mine->id, 'bill_id' => $theirBill->id])->assertUnprocessable();
    $this->actingAs($me)->postJson('/expenses', [...$base, 'budget_category_id' => $mine->id])->assertCreated();
});

test('bills cannot use another family\'s category', function () {
    $me = createAdminUser();
    $theirCat = BudgetCategory::factory()->create(['user_id' => foreignFamily()->id]);

    $this->actingAs($me)->postJson('/bills', [
        'name' => 'x', 'amount' => 5, 'budget_category_id' => $theirCat->id,
        'start_date' => now()->toDateString(), 'frequency' => 'monthly',
    ])->assertUnprocessable();
});

test('meal plans and cookbooks cannot use another family\'s recipe', function () {
    $me = createAdminUser();
    $theirRecipe = Recipe::factory()->create(['user_id' => foreignFamily()->id, 'name' => 'SECRET']);
    $myCookbook = Cookbook::factory()->create(['user_id' => $me->id]);

    $this->actingAs($me)->postJson('/meal-plan-entries', [
        'date' => now()->toDateString(), 'meal_type' => 'dinner', 'recipe_id' => $theirRecipe->id, 'name' => 'x',
    ])->assertUnprocessable();

    $this->actingAs($me)->postJson("/cookbooks/{$myCookbook->id}/recipes", ['recipe_id' => $theirRecipe->id])->assertUnprocessable();
});

test('events cannot reference another family\'s members or attendees', function () {
    $me = createAdminUser();
    $them = foreignFamily();
    $theirMember = $them->familyMembers()->first();
    $payload = [
        'title' => 'x', 'start_date' => now()->toDateString(), 'end_date' => now()->toDateString(),
        'start_time' => '10:00', 'end_time' => '11:00', 'timezone' => 'UTC',
    ];

    $this->actingAs($me)->postJson('/events', [...$payload, 'family_member_ids' => [$theirMember->id]])->assertUnprocessable();
    $this->actingAs($me)->postJson('/events', [...$payload, 'attendee_ids' => [$them->id]])->assertUnprocessable();
    $this->actingAs($me)->postJson('/events', $payload)->assertCreated();
});

test('the calendar feed endpoint tolerates bad input', function () {
    $me = createAdminUser();

    $this->actingAs($me)->getJson('/events?start=2026-01-01&end=2026-02-01&timezone=Nope/Zone')->assertUnprocessable();
    $this->actingAs($me)->getJson('/events?start=2000-01-01&end=2100-01-01&timezone=UTC')->assertUnprocessable();
    $this->actingAs($me)->getJson('/events?start=2026-01-01&end=2026-02-01&timezone=UTC')->assertOk();
});

test('an event with a bogus timezone falls back to UTC instead of erroring', function () {
    $me = createAdminUser();

    $this->actingAs($me)->postJson('/events', [
        'title' => 'x', 'start_date' => now()->toDateString(), 'end_date' => now()->toDateString(),
        'start_time' => '10:00', 'end_time' => '11:00', 'timezone' => 'Nope/Zone',
    ])->assertCreated();
});
