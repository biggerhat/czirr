<?php

namespace Database\Seeders;

use App\Models\BudgetCategory;
use App\Models\Expense;
use App\Models\User;
use App\Services\BudgetService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class BudgetSeeder extends Seeder
{
    public int $billCount = 12;

    public int $expenseCount = 40;

    public int $incomeCount = 3;

    public int $monthsOfHistory = 3;

    private const BILLS_BY_CATEGORY = [
        'Housing' => [
            ['name' => 'Rent', 'amount' => [1200, 2200], 'frequency' => 'monthly'],
            ['name' => 'HOA Fees', 'amount' => [150, 400], 'frequency' => 'monthly'],
            ['name' => 'Mortgage', 'amount' => [1500, 2800], 'frequency' => 'monthly'],
        ],
        'Utilities' => [
            ['name' => 'Electric', 'amount' => [80, 200], 'frequency' => 'monthly'],
            ['name' => 'Water', 'amount' => [30, 80], 'frequency' => 'monthly'],
            ['name' => 'Gas', 'amount' => [40, 120], 'frequency' => 'monthly'],
            ['name' => 'Internet', 'amount' => [50, 100], 'frequency' => 'monthly'],
            ['name' => 'Phone', 'amount' => [40, 90], 'frequency' => 'monthly'],
            ['name' => 'Trash', 'amount' => [20, 50], 'frequency' => 'monthly'],
        ],
        'Subscriptions' => [
            ['name' => 'Netflix', 'amount' => [10, 23], 'frequency' => 'monthly'],
            ['name' => 'Spotify', 'amount' => [10, 17], 'frequency' => 'monthly'],
            ['name' => 'Disney+', 'amount' => [8, 14], 'frequency' => 'monthly'],
            ['name' => 'YouTube Premium', 'amount' => [12, 14], 'frequency' => 'monthly'],
            ['name' => 'iCloud Storage', 'amount' => [1, 10], 'frequency' => 'monthly'],
            ['name' => 'Gym Membership', 'amount' => [25, 60], 'frequency' => 'monthly'],
            ['name' => 'Amazon Prime', 'amount' => [130, 150], 'frequency' => 'yearly'],
        ],
        'Transportation' => [
            ['name' => 'Car Payment', 'amount' => [250, 550], 'frequency' => 'monthly'],
            ['name' => 'Car Insurance', 'amount' => [120, 300], 'frequency' => 'monthly'],
            ['name' => 'Gas / Fuel', 'amount' => [100, 250], 'frequency' => 'monthly'],
            ['name' => 'Transit Pass', 'amount' => [50, 130], 'frequency' => 'monthly'],
        ],
        'Insurance' => [
            ['name' => 'Health Insurance', 'amount' => [300, 800], 'frequency' => 'monthly'],
            ['name' => 'Life Insurance', 'amount' => [30, 80], 'frequency' => 'monthly'],
            ['name' => 'Renters Insurance', 'amount' => [15, 40], 'frequency' => 'monthly'],
            ['name' => 'Home Insurance', 'amount' => [800, 2000], 'frequency' => 'yearly'],
        ],
        'Healthcare' => [
            ['name' => 'Dental Plan', 'amount' => [20, 60], 'frequency' => 'monthly'],
            ['name' => 'Vision Plan', 'amount' => [10, 30], 'frequency' => 'monthly'],
            ['name' => 'Prescription', 'amount' => [15, 80], 'frequency' => 'monthly'],
        ],
        'Food' => [
            ['name' => 'Meal Kit Subscription', 'amount' => [50, 120], 'frequency' => 'monthly'],
        ],
        'Entertainment' => [
            ['name' => 'Streaming Bundle', 'amount' => [20, 40], 'frequency' => 'monthly'],
        ],
    ];

    private const EXPENSE_NAMES_BY_CATEGORY = [
        'Food' => ['Costco', 'Trader Joe\'s', 'Safeway', 'Whole Foods', 'Target', 'Walmart', 'Aldi', 'Kroger'],
        'Entertainment' => ['Movie tickets', 'Bowling', 'Mini golf', 'Arcade', 'Escape room', 'Concert tickets', 'Museum entry', 'Theme park'],
        'Transportation' => ['Uber', 'Lyft', 'Parking', 'Toll', 'Oil change', 'Car wash', 'Tire rotation'],
        'Healthcare' => ['Pharmacy', 'Copay', 'Lab fees', 'Urgent care', 'Eye exam'],
        'Housing' => ['Hardware store', 'Plumber', 'Lawn care', 'Cleaning supplies'],
        'Utilities' => ['Extra data', 'Equipment fee'],
        'Subscriptions' => ['App purchase', 'Domain renewal'],
        'Insurance' => ['Deductible', 'Copay'],
    ];

    private const INCOME_TEMPLATES = [
        ['name' => 'Salary', 'amount' => [3500, 6000], 'frequency' => 'monthly'],
        ['name' => 'Spouse Salary', 'amount' => [3000, 5500], 'frequency' => 'monthly'],
        ['name' => 'Freelance', 'amount' => [500, 2000], 'frequency' => 'monthly'],
        ['name' => 'Side Project', 'amount' => [200, 800], 'frequency' => 'monthly'],
        ['name' => 'Consulting', 'amount' => [1000, 3000], 'frequency' => 'monthly'],
        ['name' => 'Paycheck', 'amount' => [1800, 3200], 'frequency' => 'biweekly'],
        ['name' => 'Rental Income', 'amount' => [800, 2000], 'frequency' => 'monthly'],
        ['name' => 'Dividends', 'amount' => [200, 1000], 'frequency' => 'quarterly'],
    ];

    public function run(): void
    {
        $user = User::where('email', 'test@example.com')->first() ?? User::first();

        if (! $user) {
            $this->command?->error('No user found. Run DatabaseSeeder first.');

            return;
        }

        $budgetService = app(BudgetService::class);
        $budgetService->seedDefaultCategories($user);

        $categories = BudgetCategory::where('user_id', $user->id)
            ->pluck('id', 'name');

        $now = Carbon::now();
        $historyStart = $now->copy()->subMonths($this->monthsOfHistory)->startOfMonth();

        // --- Bills ---
        $bills = $this->seedBills($user, $categories, $budgetService, $historyStart);

        // --- Expenses from paid bills ---
        $this->seedBillExpenses($user, $bills, $now);

        // --- Miscellaneous expenses ---
        $this->seedMiscExpenses($user, $categories, $now);

        // --- Incomes ---
        $this->seedIncomes($user, $budgetService, $historyStart);

        $billCount = count($bills);
        $this->command?->info("Seeded budget data: {$billCount} bills, {$this->expenseCount} misc expenses (+ bill payments for {$this->monthsOfHistory} months), {$this->incomeCount} incomes.");
    }

    private function seedBills(User $user, $categories, BudgetService $budgetService, Carbon $historyStart): array
    {
        $pool = [];
        foreach (self::BILLS_BY_CATEGORY as $catName => $templates) {
            if (! $categories->has($catName)) {
                continue;
            }
            foreach ($templates as $template) {
                $pool[] = array_merge($template, ['category_id' => $categories[$catName]]);
            }
        }

        shuffle($pool);
        $selected = array_slice($pool, 0, $this->billCount);

        $bills = [];
        foreach ($selected as $template) {
            $amount = fake()->randomFloat(2, $template['amount'][0], $template['amount'][1]);
            $startDay = rand(1, 28);
            $startDate = $historyStart->copy()->day($startDay);

            $bill = $budgetService->createBillWithEvent($user, [
                'budget_category_id' => $template['category_id'],
                'name' => $template['name'],
                'amount' => $amount,
                'start_date' => $startDate->format('Y-m-d'),
                'frequency' => $template['frequency'],
                'is_active' => true,
            ]);

            $bills[] = $bill;
        }

        return $bills;
    }

    private function seedBillExpenses(User $user, array $bills, Carbon $now): void
    {
        foreach ($bills as $bill) {
            if ($bill->frequency === 'yearly' || $bill->frequency === 'quarterly') {
                continue;
            }

            for ($m = $this->monthsOfHistory; $m >= 1; $m--) {
                $month = $now->copy()->subMonths($m);
                $dueDay = min($bill->start_date->day, $month->daysInMonth);
                $payDate = $month->copy()->day($dueDay);

                // Slight variation in amount for non-fixed bills
                $amount = fake()->boolean(70)
                    ? $bill->amount
                    : fake()->randomFloat(2, $bill->amount * 0.9, $bill->amount * 1.1);

                Expense::factory()->create([
                    'user_id' => $user->id,
                    'budget_category_id' => $bill->budget_category_id,
                    'bill_id' => $bill->id,
                    'name' => $bill->name,
                    'amount' => $amount,
                    'date' => $payDate,
                ]);
            }
        }
    }

    private function seedMiscExpenses(User $user, $categories, Carbon $now): void
    {
        $catNames = $categories->keys()->all();

        for ($i = 0; $i < $this->expenseCount; $i++) {
            $catName = fake()->randomElement($catNames);
            $catId = $categories[$catName];

            $names = self::EXPENSE_NAMES_BY_CATEGORY[$catName]
                ?? self::EXPENSE_NAMES_BY_CATEGORY['Food'];
            $name = fake()->randomElement($names);

            $amount = match ($catName) {
                'Food' => fake()->randomFloat(2, 20, 200),
                'Entertainment' => fake()->randomFloat(2, 10, 120),
                'Transportation' => fake()->randomFloat(2, 5, 80),
                'Healthcare' => fake()->randomFloat(2, 15, 300),
                'Housing' => fake()->randomFloat(2, 10, 250),
                default => fake()->randomFloat(2, 5, 100),
            };

            $date = fake()->dateTimeBetween(
                "-{$this->monthsOfHistory} months",
                'now'
            )->format('Y-m-d');

            Expense::factory()->create([
                'user_id' => $user->id,
                'budget_category_id' => $catId,
                'name' => $name,
                'amount' => $amount,
                'date' => $date,
                'notes' => fake()->boolean(15) ? fake()->sentence() : null,
            ]);
        }
    }

    private function seedIncomes(User $user, BudgetService $budgetService, Carbon $historyStart): void
    {
        $pool = self::INCOME_TEMPLATES;
        shuffle($pool);
        $selected = array_slice($pool, 0, $this->incomeCount);

        foreach ($selected as $i => $template) {
            $amount = fake()->randomFloat(2, $template['amount'][0], $template['amount'][1]);
            $startDay = rand(1, 28);
            $startDate = $historyStart->copy()->day($startDay);

            $budgetService->createIncomeWithEvent($user, [
                'name' => $template['name'],
                'amount' => $amount,
                'start_date' => $startDate->format('Y-m-d'),
                'frequency' => $template['frequency'],
                'is_active' => $i < $this->incomeCount - 1 || fake()->boolean(80),
            ]);
        }
    }
}
