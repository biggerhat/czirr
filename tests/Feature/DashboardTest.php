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

test('dashboard shows today\'s bills', function () {
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
            ->has('todaysBills')
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

test('today follows the user timezone, not the server', function () {
    $user = createAdminUser();
    $user->update(['timezone' => 'Pacific/Auckland']);

    // 2026-03-10 20:00 UTC is already the morning of the 11th in Auckland.
    $this->travelTo(\Carbon\Carbon::parse('2026-03-10 20:00:00', 'UTC'));

    expect($user->fresh()->localNow()->toDateString())->toBe('2026-03-11');
    $this->actingAs($user)->get('/dashboard')->assertOk();
});

test('the browser timezone is adopted only while on the UTC default', function () {
    $user = createAdminUser();

    $this->actingAs($user)->postJson('/settings/timezone/detect', ['timezone' => 'America/Chicago'])
        ->assertOk()->assertJson(['timezone' => 'America/Chicago']);

    $this->actingAs($user)->postJson('/settings/timezone/detect', ['timezone' => 'Europe/Paris'])
        ->assertOk()->assertJson(['timezone' => 'America/Chicago']);

    $this->actingAs($user)->postJson('/settings/timezone/detect', ['timezone' => 'Nope/Zone'])->assertUnprocessable();
});

test('dashboard surfaces pending approvals and the latest board post for managers', function () {
    $user = createAdminUser();
    $member = FamilyMember::factory()->create(['user_id' => $user->id]);
    $chore = Chore::factory()->create(['user_id' => $user->id, 'requires_approval' => true]);
    $assignment = ChoreAssignment::factory()->create(['chore_id' => $chore->id, 'family_member_id' => $member->id]);
    \App\Models\ChoreCompletion::create([
        'chore_assignment_id' => $assignment->id,
        'family_member_id' => $member->id,
        'completed_date' => now()->toDateString(),
        'points_earned' => 3,
        'status' => \App\Models\ChoreCompletion::PENDING,
    ]);
    \App\Models\BoardPost::create(['user_id' => $user->id, 'author_id' => $user->id, 'body' => 'Pizza night Friday', 'is_pinned' => false]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('pendingApprovals', 1)
            ->where('latestPost.body', 'Pizza night Friday'));
});

test('dashboard shows the viewer\'s own savings balance only when they have entries', function () {
    $user = createAdminUser();
    $member = FamilyMember::where('linked_user_id', $user->id)->first();

    $this->actingAs($user)->get(route('dashboard'))->assertInertia(fn ($p) => $p->where('mySavings', null));

    $user->savingsEntries()->create(['family_member_id' => $member->id, 'amount' => 12.5, 'entry_date' => now()->toDateString()]);

    $this->actingAs($user)->get(route('dashboard'))->assertInertia(fn ($p) => $p->where('mySavings', 12.5));
});
