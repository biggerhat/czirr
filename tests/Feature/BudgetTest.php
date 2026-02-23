<?php

use App\Models\BudgetCategory;

test('guests are redirected to the login page', function () {
    $this->get(route('budgeting.index'))->assertRedirect(route('login'));
});

test('authenticated user with permission can view budget', function () {
    $user = createAdminUser();

    $this->actingAs($user)
        ->get(route('budgeting.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('budgeting/Index'));
});

test('budget seeds default categories for new user', function () {
    $user = createAdminUser();

    expect(BudgetCategory::where('user_id', $user->id)->count())->toBe(0);

    $this->actingAs($user)->get(route('budgeting.index'));

    expect(BudgetCategory::where('user_id', $user->id)->count())->toBeGreaterThan(0);
});

test('budget accepts month parameter', function () {
    $user = createAdminUser();

    $this->actingAs($user)
        ->get(route('budgeting.index', ['month' => '2026-01']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('budgeting/Index'));
});

test('child role cannot view budget', function () {
    $user = createUserWithRole('child');

    $this->actingAs($user)
        ->get(route('budgeting.index'))
        ->assertForbidden();
});
