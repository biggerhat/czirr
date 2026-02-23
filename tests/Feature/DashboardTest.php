<?php

use App\Models\Bill;
use App\Models\BudgetCategory;
use App\Models\Chore;
use App\Models\ChoreAssignment;
use App\Models\FamilyMember;
use Carbon\Carbon;

test('guests are redirected to the login page', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = createAdminUser();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Dashboard'));
});

test('dashboard shows upcoming bills', function () {
    $user = createAdminUser();
    $category = BudgetCategory::factory()->create(['user_id' => $user->id]);
    Bill::factory()->create([
        'user_id' => $user->id,
        'budget_category_id' => $category->id,
        'name' => 'Internet Bill',
        'is_active' => true,
        'start_date' => Carbon::today()->format('Y-m-d'),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('upcomingBills')
        );
});

test('dashboard shows today\'s chores', function () {
    $user = createAdminUser();
    $member = FamilyMember::where('user_id', $user->id)->first();
    $chore = Chore::factory()->create(['user_id' => $user->id, 'name' => 'Wash dishes']);
    ChoreAssignment::factory()->create([
        'chore_id' => $chore->id,
        'family_member_id' => $member->id,
        'day_of_week' => Carbon::now()->dayOfWeek,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('todaysChores', 1)
        );
});
