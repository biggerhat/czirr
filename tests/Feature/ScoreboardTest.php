<?php

use App\Models\StreakBonus;

test('guests are redirected to the login page', function () {
    $this->get('/scoreboard')->assertRedirect(route('login'));
});

test('authenticated user can view scoreboard', function () {
    $user = createAdminUser();

    $this->actingAs($user)
        ->get('/scoreboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Scoreboard'));
});

test('seeds default streak bonuses on first visit', function () {
    $user = createAdminUser();

    expect(StreakBonus::where('user_id', $user->id)->count())->toBe(0);

    $this->actingAs($user)->get('/scoreboard')->assertOk();

    expect(StreakBonus::where('user_id', $user->id)->count())->toBe(4);
});

test('does not duplicate streak bonuses on subsequent visits', function () {
    $user = createAdminUser();

    $this->actingAs($user)->get('/scoreboard')->assertOk();
    $this->actingAs($user)->get('/scoreboard')->assertOk();

    expect(StreakBonus::where('user_id', $user->id)->count())->toBe(4);
});

test('week navigation parameter works', function () {
    $user = createAdminUser();

    $this->actingAs($user)
        ->get('/scoreboard?week=2026-01-05')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Scoreboard')
            ->where('weekStart', '2026-01-05')
        );
});

test('returns scoreboard data structure', function () {
    $user = createAdminUser();

    $this->actingAs($user)
        ->get('/scoreboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Scoreboard')
            ->has('scoreboard.weekly')
            ->has('scoreboard.overall')
            ->has('scoreboard.milestones')
            ->has('bonusObjectives')
            ->has('weekStart')
            ->has('can')
        );
});

test('child role can view scoreboard', function () {
    $user = createUserWithRole('child');

    $this->actingAs($user)
        ->get('/scoreboard')
        ->assertOk();
});
