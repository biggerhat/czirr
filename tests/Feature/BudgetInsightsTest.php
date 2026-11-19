<?php

use App\Models\BudgetCategory;
use App\Models\Expense;
use App\Models\Income;
use App\Services\BudgetInsightsService;
use Carbon\Carbon;

test('the trend covers six months and totals expenses per month', function () {
    $owner = createAdminUser();
    $cat = BudgetCategory::factory()->create(['user_id' => $owner->id, 'name' => 'Food']);
    $month = Carbon::parse('2026-06-15');

    Expense::factory()->create(['user_id' => $owner->id, 'budget_category_id' => $cat->id, 'amount' => 100, 'date' => '2026-06-03']);
    Expense::factory()->create(['user_id' => $owner->id, 'budget_category_id' => $cat->id, 'amount' => 50, 'date' => '2026-06-20']);
    Expense::factory()->create(['user_id' => $owner->id, 'budget_category_id' => $cat->id, 'amount' => 80, 'date' => '2026-05-10']);
    Expense::factory()->create(['user_id' => $owner->id, 'budget_category_id' => $cat->id, 'amount' => 999, 'date' => '2025-01-10']); // outside the window

    $out = app(BudgetInsightsService::class)->build($owner, $month);

    expect($out['trend'])->toHaveCount(6)
        ->and($out['trend'][5])->toMatchArray(['month' => '2026-06', 'spent' => 150.0])
        ->and($out['trend'][4])->toMatchArray(['month' => '2026-05', 'spent' => 80.0])
        ->and($out['trend'][0]['month'])->toBe('2026-01')
        ->and($out['average_spent'])->toBe(115.0);
});

test('categories show share and change versus last month', function () {
    $owner = createAdminUser();
    $food = BudgetCategory::factory()->create(['user_id' => $owner->id, 'name' => 'Food']);
    $fun = BudgetCategory::factory()->create(['user_id' => $owner->id, 'name' => 'Fun']);

    Expense::factory()->create(['user_id' => $owner->id, 'budget_category_id' => $food->id, 'amount' => 150, 'date' => '2026-06-05']);
    Expense::factory()->create(['user_id' => $owner->id, 'budget_category_id' => $fun->id, 'amount' => 50, 'date' => '2026-06-06']);
    Expense::factory()->create(['user_id' => $owner->id, 'budget_category_id' => $food->id, 'amount' => 100, 'date' => '2026-05-05']);

    $cats = app(BudgetInsightsService::class)->build($owner, Carbon::parse('2026-06-10'))['categories'];

    expect($cats[0])->toMatchArray(['name' => 'Food', 'total' => 150.0, 'previous' => 100.0, 'share' => 75.0, 'change' => 50.0])
        ->and($cats[1])->toMatchArray(['name' => 'Fun', 'share' => 25.0, 'change' => null]);
});

test('income uses scheduled occurrences and the other family is excluded', function () {
    $owner = createAdminUser();
    $other = createAdminUser();
    Income::factory()->create(['user_id' => $owner->id, 'amount' => 2000, 'frequency' => 'monthly', 'start_date' => '2026-01-01', 'is_active' => true]);
    $cat = BudgetCategory::factory()->create(['user_id' => $other->id]);
    Expense::factory()->create(['user_id' => $other->id, 'budget_category_id' => $cat->id, 'amount' => 5000, 'date' => '2026-06-05']);

    $out = app(BudgetInsightsService::class)->build($owner, Carbon::parse('2026-06-10'));

    expect($out['trend'][5]['income'])->toBe(2000.0)
        ->and($out['trend'][5]['spent'])->toBe(0.0)
        ->and($out['trend'][5]['saved'])->toBe(2000.0)
        ->and($out['categories'])->toBe([]);
});

test('the budget page defers insights and still renders', function () {
    $owner = createAdminUser();

    $this->actingAs($owner)->get('/budgeting')->assertOk()
        ->assertInertia(fn ($p) => $p->component('budgeting/Index')->missing('insights'));
});
