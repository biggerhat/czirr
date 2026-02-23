<?php

use App\Models\BudgetCategory;

test('guests are redirected to the login page', function () {
    $this->get(route('calendar.index'))->assertRedirect(route('login'));
});

test('authenticated user can view calendar', function () {
    $user = createAdminUser();

    $this->actingAs($user)
        ->get(route('calendar.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('calendar/Index'));
});

test('calendar seeds default budget categories for new user', function () {
    $user = createAdminUser();

    expect(BudgetCategory::where('user_id', $user->id)->count())->toBe(0);

    $this->actingAs($user)->get(route('calendar.index'));

    expect(BudgetCategory::where('user_id', $user->id)->count())->toBeGreaterThan(0);
});
