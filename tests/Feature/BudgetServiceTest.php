<?php

use App\Models\BudgetCategory;
use App\Models\Event;
use App\Models\Expense;
use App\Models\User;
use App\Services\BudgetService;
use Carbon\Carbon;

beforeEach(function () {
    $this->svc = new BudgetService;
    $this->user = User::factory()->create();
    $this->category = BudgetCategory::factory()->create(['user_id' => $this->user->id]);
});

function billData(array $over = []): array
{
    return array_merge([
        'budget_category_id' => test()->category->id,
        'name' => 'Rent',
        'amount' => 1200,
        'start_date' => '2030-01-05',
        'frequency' => 'monthly',
        'is_active' => true,
    ], $over);
}

test('default categories are seeded once', function () {
    $fresh = User::factory()->create();

    $this->svc->seedDefaultCategories($fresh);
    $count = $fresh->budgetCategories()->count();
    $this->svc->seedDefaultCategories($fresh);

    expect($count)->toBeGreaterThan(0)->and($fresh->budgetCategories()->count())->toBe($count);
});

test('creating an active bill also creates a recurring calendar event', function () {
    $bill = $this->svc->createBillWithEvent($this->user, billData());

    $event = Event::find($bill->event_id);
    expect($event)->not->toBeNull()
        ->and($event->source)->toBe('bill')
        ->and($event->title)->toBe('Rent due')
        ->and($event->rrule)->toContain('FREQ=MONTHLY');
});

test('inactive bills get no event, and toggling active syncs it', function () {
    $bill = $this->svc->createBillWithEvent($this->user, billData(['is_active' => false]));
    expect($bill->event_id)->toBeNull();

    $bill = $this->svc->updateBillEvent($bill, ['is_active' => true]);
    expect($bill->event_id)->not->toBeNull();
    $eventId = $bill->event_id;

    $bill = $this->svc->updateBillEvent($bill, ['is_active' => false]);
    expect($bill->event_id)->toBeNull()->and(Event::find($eventId))->toBeNull();
});

test('renaming a bill updates its event', function () {
    $bill = $this->svc->createBillWithEvent($this->user, billData());

    $this->svc->updateBillEvent($bill, ['name' => 'Mortgage']);

    expect(Event::find($bill->event_id)->title)->toBe('Mortgage due');
});

test('deleting a bill event clears the link', function () {
    $bill = $this->svc->createBillWithEvent($this->user, billData());
    $eventId = $bill->event_id;

    $this->svc->deleteBillEvent($bill);

    expect($bill->fresh()->event_id)->toBeNull()->and(Event::find($eventId))->toBeNull();
});

test('monthly overview lists bills due that month and flags paid ones', function () {
    $rent = $this->svc->createBillWithEvent($this->user, billData());
    $this->svc->createBillWithEvent($this->user, billData(['name' => 'Starts later', 'start_date' => '2031-06-01']));

    Expense::factory()->create([
        'user_id' => $this->user->id,
        'budget_category_id' => $this->category->id,
        'bill_id' => $rent->id,
        'date' => '2030-03-06',
    ]);

    $march = $this->svc->getMonthlyOverview($this->user, Carbon::parse('2030-03-15'));
    $april = $this->svc->getMonthlyOverview($this->user, Carbon::parse('2030-04-15'));

    expect($march['month'])->toBe('2030-03')
        ->and($march['bills'])->toHaveCount(1)
        ->and($march['bills'][0]->is_paid_this_month)->toBeTrue()
        ->and($march['expenses'])->toHaveCount(1)
        ->and($april['bills'][0]->is_paid_this_month)->toBeFalse();
});

test('overview only includes this user\'s data', function () {
    $this->svc->createBillWithEvent($this->user, billData());
    $other = User::factory()->create();

    $overview = $this->svc->getMonthlyOverview($other, Carbon::parse('2030-03-15'));

    expect($overview['bills'])->toHaveCount(0);
});
