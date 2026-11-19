<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Read-only summaries on top of BudgetService: a month-by-month trend and where
 * this month's money went compared with last month. "Spent" is recorded expenses
 * (bill payments are logged as expenses too); "income" is expected income.
 */
class BudgetInsightsService
{
    public function __construct(private BudgetService $budget) {}

    /**
     * @return array{
     *     trend: list<array{month: string, label: string, income: float, spent: float, saved: float}>,
     *     categories: list<array{name: string, color: string|null, total: float, previous: float, share: float, change: float|null}>,
     *     average_spent: float,
     *     month: string
     * }
     */
    public function build(User $owner, CarbonInterface $month, int $months = 6): array
    {
        $month = Carbon::instance($month)->startOfMonth();

        $trend = [];
        $current = null;
        $previous = null;

        for ($i = $months - 1; $i >= 0; $i--) {
            $m = $month->copy()->subMonthsNoOverflow($i);
            $overview = $this->budget->getMonthlyOverview($owner, $m);

            $income = $this->sumOccurrences($overview['incomes']);
            $spent = (float) $overview['expenses']->sum('amount');

            $trend[] = [
                'month' => $m->format('Y-m'),
                'label' => $m->format('M'),
                'income' => round($income, 2),
                'spent' => round($spent, 2),
                'saved' => round($income - $spent, 2),
            ];

            if ($i === 0) {
                $current = $overview['expenses'];
            }
            if ($i === 1) {
                $previous = $overview['expenses'];
            }
        }

        $categories = $this->categoryBreakdown($current ?? collect(), $previous ?? collect());

        $withSpend = array_filter($trend, fn ($t) => $t['spent'] > 0);

        return [
            'trend' => $trend,
            'categories' => $categories,
            'average_spent' => $withSpend ? round(array_sum(array_column($withSpend, 'spent')) / count($withSpend), 2) : 0.0,
            'month' => $month->format('Y-m'),
        ];
    }

    /** @param  Collection<int, mixed>  $items */
    private function sumOccurrences(Collection $items): float
    {
        return (float) $items->sum(fn ($i) => (float) $i->amount * (int) $i->getAttribute('occurrences_in_range'));
    }

    /**
     * @param  Collection<int, mixed>  $current
     * @param  Collection<int, mixed>  $previous
     * @return list<array{name: string, color: string|null, total: float, previous: float, share: float, change: float|null}>
     */
    private function categoryBreakdown(Collection $current, Collection $previous): array
    {
        $prevTotals = $previous->groupBy('budget_category_id')->map(fn ($g) => (float) $g->sum('amount'));
        $grand = (float) $current->sum('amount');

        return $current->groupBy('budget_category_id')
            ->map(function ($group, $categoryId) use ($prevTotals, $grand) {
                $total = (float) $group->sum('amount');
                $prev = (float) ($prevTotals[$categoryId] ?? 0);

                return [
                    'name' => $group->first()->category->name ?? 'Uncategorised',
                    'color' => $group->first()->category->color ?? null,
                    'total' => round($total, 2),
                    'previous' => round($prev, 2),
                    'share' => $grand > 0 ? round($total / $grand * 100, 1) : 0.0,
                    // null when there is nothing to compare against
                    'change' => $prev > 0 ? round(($total - $prev) / $prev * 100, 1) : null,
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->all();
    }
}
