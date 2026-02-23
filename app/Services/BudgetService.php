<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Event;
use App\Models\Income;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class BudgetService
{
    private const DEFAULT_CATEGORIES = [
        ['name' => 'Housing', 'color' => 'blue', 'sort_order' => 0],
        ['name' => 'Utilities', 'color' => 'cyan', 'sort_order' => 1],
        ['name' => 'Subscriptions', 'color' => 'violet', 'sort_order' => 2],
        ['name' => 'Food', 'color' => 'emerald', 'sort_order' => 3],
        ['name' => 'Transportation', 'color' => 'amber', 'sort_order' => 4],
        ['name' => 'Insurance', 'color' => 'orange', 'sort_order' => 5],
        ['name' => 'Healthcare', 'color' => 'rose', 'sort_order' => 6],
        ['name' => 'Entertainment', 'color' => 'pink', 'sort_order' => 7],
        ['name' => 'Other', 'color' => 'blue', 'sort_order' => 8],
    ];

    public function seedDefaultCategories(User $user): void
    {
        if ($user->budgetCategories()->exists()) {
            return;
        }

        foreach (self::DEFAULT_CATEGORIES as $category) {
            $user->budgetCategories()->create($category);
        }
    }

    public function createBillWithEvent(User $user, array $data): Bill
    {
        /** @var Bill $bill */
        $bill = $user->bills()->create($data);

        if ($bill->is_active) {
            $this->createEventForBill($user, $bill);
        }

        return $bill->load('category');
    }

    public function updateBillEvent(Bill $bill, array $data): Bill
    {
        $wasActive = $bill->is_active;
        $bill->update($data);
        $bill->refresh();

        if ($bill->is_active && $bill->event_id) {
            $this->syncEventWithBill($bill);
        } elseif ($bill->is_active && !$bill->event_id) {
            $this->createEventForBill($bill->user, $bill);
        } elseif (!$bill->is_active && $bill->event_id) {
            $this->deleteBillEvent($bill);
        }

        return $bill->load('category');
    }

    public function deleteBillEvent(Bill $bill): void
    {
        if ($bill->event_id) {
            Event::where('id', $bill->event_id)->delete();
            $bill->update(['event_id' => null]);
        }
    }

    public function createIncomeWithEvent(User $user, array $data): Income
    {
        /** @var Income $income */
        $income = $user->incomes()->create($data);

        if ($income->is_active) {
            $this->createEventForIncome($user, $income);
        }

        return $income;
    }

    public function updateIncomeEvent(Income $income, array $data): Income
    {
        $income->update($data);
        $income->refresh();

        if ($income->is_active && $income->event_id) {
            $this->syncEventWithIncome($income);
        } elseif ($income->is_active && !$income->event_id) {
            $this->createEventForIncome($income->user, $income);
        } elseif (!$income->is_active && $income->event_id) {
            $this->deleteIncomeEvent($income);
        }

        return $income;
    }

    public function deleteIncomeEvent(Income $income): void
    {
        if ($income->event_id) {
            Event::where('id', $income->event_id)->delete();
            $income->update(['event_id' => null]);
        }
    }

    public function getMonthlyOverview(User $user, CarbonInterface $month): array
    {
        $month = Carbon::instance($month);

        return $this->getOverview(
            $user,
            $month->copy()->startOfMonth(),
            $month->copy()->endOfMonth(),
            $month->format('Y-m'),
        );
    }

    public function getOverviewForRange(User $user, Carbon $start, Carbon $end): array
    {
        return $this->getOverview($user, $start, $end, $start->format('Y-m'));
    }

    private function getOverview(User $user, Carbon $start, Carbon $end, string $month): array
    {
        $bills = $user->bills()
            ->with('category')
            ->orderBy('start_date')
            ->get()
            ->filter(fn (Bill $bill) => $bill->isDueInRange($start, $end))
            ->map(function (Bill $bill) use ($start, $end) {
                $bill->setAttribute('is_paid_this_month', $bill->isPaidForRange($start, $end));
                return $bill;
            })
            ->values();

        $expenses = $user->expenses()
            ->with('category')
            ->whereBetween('date', [$start, $end])
            ->orderBy('date', 'desc')
            ->get();

        $incomes = $user->incomes()
            ->orderBy('start_date')
            ->get()
            ->filter(fn (Income $income) => $income->hasOccurrenceInRange($start, $end))
            ->values();

        $categories = $user->budgetCategories()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return [
            'bills' => $bills,
            'expenses' => $expenses,
            'incomes' => $incomes,
            'categories' => $categories,
            'month' => $month,
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
        ];
    }

    private function createEventForBill(User $user, Bill $bill): void
    {
        $startsAt = $bill->start_date->copy()->midDay();
        $rrule = $this->buildRRule($bill->frequency, $bill->start_date->day);

        $event = Event::create([
            'user_id' => $user->id,
            'title' => "{$bill->name} due",
            'description' => null,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy(),
            'is_all_day' => true,
            'rrule' => $rrule,
            'source' => 'bill',
        ]);

        $bill->update(['event_id' => $event->id]);
    }

    private function syncEventWithBill(Bill $bill): void
    {
        $event = $bill->event;
        if (!$event) {
            return;
        }

        $startsAt = $bill->start_date->copy()->midDay();
        $rrule = $this->buildRRule($bill->frequency, $bill->start_date->day);

        $event->update([
            'title' => "{$bill->name} due",
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy(),
            'rrule' => $rrule,
        ]);
    }

    private function createEventForIncome(User $user, Income $income): void
    {
        $startsAt = $income->start_date->copy()->midDay();
        $rrule = $this->buildIncomeRRule($income->frequency, $income->start_date->day, $startsAt);

        $event = Event::create([
            'user_id' => $user->id,
            'title' => "{$income->name}",
            'description' => null,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy(),
            'is_all_day' => true,
            'rrule' => $rrule,
            'source' => 'income',
        ]);

        $income->update(['event_id' => $event->id]);
    }

    private function syncEventWithIncome(Income $income): void
    {
        $event = $income->event;
        if (!$event) {
            return;
        }

        $startsAt = $income->start_date->copy()->midDay();
        $rrule = $this->buildIncomeRRule($income->frequency, $income->start_date->day, $startsAt);

        $event->update([
            'title' => "{$income->name}",
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy(),
            'rrule' => $rrule,
        ]);
    }

    private function buildRRule(string $frequency, int $dueDay): string
    {
        // BYMONTHDAY pins recurrence to the exact day-of-month regardless of
        // timezone shifts applied to DTSTART during expansion.
        return match ($frequency) {
            'monthly' => "FREQ=MONTHLY;BYMONTHDAY={$dueDay}",
            'quarterly' => "FREQ=MONTHLY;INTERVAL=3;BYMONTHDAY={$dueDay}",
            'yearly' => "FREQ=YEARLY;BYMONTHDAY={$dueDay}",
            default => "FREQ=MONTHLY;BYMONTHDAY={$dueDay}",
        };
    }

    private function buildIncomeRRule(string $frequency, int $payDay, CarbonInterface $startsAt): ?string
    {
        return match ($frequency) {
            'once' => null,
            'weekly' => 'FREQ=WEEKLY;BYDAY=' . strtoupper(substr($startsAt->englishDayOfWeek, 0, 2)),
            'biweekly' => 'FREQ=WEEKLY;INTERVAL=2;BYDAY=' . strtoupper(substr($startsAt->englishDayOfWeek, 0, 2)),
            'monthly' => "FREQ=MONTHLY;BYMONTHDAY={$payDay}",
            'quarterly' => "FREQ=MONTHLY;INTERVAL=3;BYMONTHDAY={$payDay}",
            'yearly' => "FREQ=YEARLY;BYMONTHDAY={$payDay}",
            default => "FREQ=MONTHLY;BYMONTHDAY={$payDay}",
        };
    }
}
