<?php

namespace Database\Seeders;

use App\Enums\FamilyRole;
use App\Enums\MealType;
use App\Models\Bill;
use App\Models\BudgetCategory;
use App\Models\Chore;
use App\Models\ChoreAssignment;
use App\Models\Contact;
use App\Models\Event;
use App\Models\Expense;
use App\Models\FamilyList;
use App\Models\FamilyListItem;
use App\Models\FamilyMember;
use App\Models\Income;
use App\Models\MealPlanEntry;
use App\Models\Recipe;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleAndPermissionSeeder::class,
            CuisineSeeder::class,
            RecipeTagSeeder::class,
        ]);

        // --- Admin user (family owner) ---
        $admin = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // --- Linked family user accounts ---
        $spouse = User::factory()->create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);

        $teen = User::factory()->create([
            'name' => 'Alex Doe',
            'email' => 'alex@example.com',
        ]);

        // --- Unlinked user (should see "Contact family admin" page) ---
        User::factory()->create([
            'name' => 'Unlinked User',
            'email' => 'unlinked@example.com',
        ]);

        // --- Assign Spatie roles ---
        $admin->assignRole('admin');
        $spouse->assignRole('parent');
        $teen->assignRole('child');

        // --- Family members ---
        $adminMember = FamilyMember::factory()->create([
            'user_id' => $admin->id,
            'name' => 'Test User',
            'nickname' => null,
            'role' => FamilyRole::Parent,
            'color' => 'blue',
            'linked_user_id' => $admin->id,
        ]);

        $spouseMember = FamilyMember::factory()->create([
            'user_id' => $admin->id,
            'name' => 'Jane Doe',
            'nickname' => 'Jane',
            'role' => FamilyRole::Parent,
            'color' => 'rose',
            'linked_user_id' => $spouse->id,
        ]);

        $teenMember = FamilyMember::factory()->create([
            'user_id' => $admin->id,
            'name' => 'Alex Doe',
            'nickname' => 'Alex',
            'role' => FamilyRole::Child,
            'color' => 'emerald',
            'linked_user_id' => $teen->id,
        ]);

        $childMember = FamilyMember::factory()->create([
            'user_id' => $admin->id,
            'name' => 'Sam Doe',
            'nickname' => 'Sammy',
            'role' => FamilyRole::Child,
            'color' => 'amber',
            'linked_user_id' => null,
        ]);

        $allMembers = [$adminMember, $spouseMember, $teenMember, $childMember];

        // --- Budget categories ---
        $categories = collect([
            ['name' => 'Housing', 'color' => 'blue'],
            ['name' => 'Utilities', 'color' => 'cyan'],
            ['name' => 'Groceries', 'color' => 'emerald'],
            ['name' => 'Transportation', 'color' => 'amber'],
            ['name' => 'Insurance', 'color' => 'violet'],
            ['name' => 'Entertainment', 'color' => 'pink'],
            ['name' => 'Healthcare', 'color' => 'rose'],
            ['name' => 'Subscriptions', 'color' => 'orange'],
        ])->map(fn ($cat, $i) => BudgetCategory::factory()->create([
            'user_id' => $admin->id,
            'name' => $cat['name'],
            'color' => $cat['color'],
            'sort_order' => $i,
        ]));

        $housing = $categories->firstWhere('name', 'Housing');
        $utilities = $categories->firstWhere('name', 'Utilities');
        $groceries = $categories->firstWhere('name', 'Groceries');
        $transport = $categories->firstWhere('name', 'Transportation');
        $insurance = $categories->firstWhere('name', 'Insurance');
        $entertainment = $categories->firstWhere('name', 'Entertainment');
        $healthcare = $categories->firstWhere('name', 'Healthcare');
        $subscriptions = $categories->firstWhere('name', 'Subscriptions');

        // --- Bills ---
        $bills = [
            Bill::factory()->create([
                'user_id' => $admin->id,
                'budget_category_id' => $housing->id,
                'name' => 'Rent',
                'amount' => 1800.00,
                'start_date' => '2025-09-01',
                'frequency' => 'monthly',
            ]),
            Bill::factory()->create([
                'user_id' => $admin->id,
                'budget_category_id' => $utilities->id,
                'name' => 'Electric',
                'amount' => 120.00,
                'start_date' => '2025-09-15',
                'frequency' => 'monthly',
            ]),
            Bill::factory()->create([
                'user_id' => $admin->id,
                'budget_category_id' => $utilities->id,
                'name' => 'Internet',
                'amount' => 65.00,
                'start_date' => '2025-09-20',
                'frequency' => 'monthly',
            ]),
            Bill::factory()->create([
                'user_id' => $admin->id,
                'budget_category_id' => $utilities->id,
                'name' => 'Water',
                'amount' => 45.00,
                'start_date' => '2025-09-10',
                'frequency' => 'monthly',
            ]),
            Bill::factory()->create([
                'user_id' => $admin->id,
                'budget_category_id' => $insurance->id,
                'name' => 'Car Insurance',
                'amount' => 280.00,
                'start_date' => '2025-09-05',
                'frequency' => 'monthly',
            ]),
            Bill::factory()->create([
                'user_id' => $admin->id,
                'budget_category_id' => $insurance->id,
                'name' => 'Health Insurance',
                'amount' => 450.00,
                'start_date' => '2025-09-01',
                'frequency' => 'monthly',
            ]),
            Bill::factory()->create([
                'user_id' => $admin->id,
                'budget_category_id' => $subscriptions->id,
                'name' => 'Netflix',
                'amount' => 15.49,
                'start_date' => '2025-09-12',
                'frequency' => 'monthly',
            ]),
            Bill::factory()->create([
                'user_id' => $admin->id,
                'budget_category_id' => $subscriptions->id,
                'name' => 'Spotify',
                'amount' => 10.99,
                'start_date' => '2025-09-18',
                'frequency' => 'monthly',
            ]),
            Bill::factory()->create([
                'user_id' => $admin->id,
                'budget_category_id' => $transport->id,
                'name' => 'Car Payment',
                'amount' => 350.00,
                'start_date' => '2025-09-15',
                'frequency' => 'monthly',
            ]),
        ];

        // --- Expenses (paid bills for past months + misc expenses) ---
        $today = Carbon::today();

        foreach ($bills as $bill) {
            // Mark last 2 months as paid
            for ($m = 2; $m >= 1; $m--) {
                $month = $today->copy()->subMonths($m);
                Expense::factory()->create([
                    'user_id' => $admin->id,
                    'budget_category_id' => $bill->budget_category_id,
                    'bill_id' => $bill->id,
                    'name' => $bill->name,
                    'amount' => $bill->amount,
                    'date' => $month->copy()->day(min($bill->start_date->day, $month->daysInMonth)),
                ]);
            }
        }

        // Misc grocery expenses
        for ($i = 0; $i < 8; $i++) {
            Expense::factory()->create([
                'user_id' => $admin->id,
                'budget_category_id' => $groceries->id,
                'name' => fake()->randomElement(['Costco', 'Trader Joe\'s', 'Safeway', 'Whole Foods', 'Target']),
                'amount' => fake()->randomFloat(2, 40, 180),
                'date' => fake()->dateTimeBetween('-2 months', 'now')->format('Y-m-d'),
            ]);
        }

        // Misc entertainment expenses
        for ($i = 0; $i < 4; $i++) {
            Expense::factory()->create([
                'user_id' => $admin->id,
                'budget_category_id' => $entertainment->id,
                'name' => fake()->randomElement(['Movie tickets', 'Bowling', 'Mini golf', 'Arcade', 'Escape room']),
                'amount' => fake()->randomFloat(2, 15, 80),
                'date' => fake()->dateTimeBetween('-2 months', 'now')->format('Y-m-d'),
            ]);
        }

        // --- Incomes ---
        Income::factory()->create([
            'user_id' => $admin->id,
            'name' => 'Salary',
            'amount' => 4200.00,
            'start_date' => '2025-06-15',
            'frequency' => 'monthly',
        ]);

        Income::factory()->create([
            'user_id' => $admin->id,
            'name' => 'Spouse Salary',
            'amount' => 3800.00,
            'start_date' => '2025-06-01',
            'frequency' => 'monthly',
        ]);

        Income::factory()->create([
            'user_id' => $admin->id,
            'name' => 'Freelance',
            'amount' => 500.00,
            'start_date' => '2025-10-01',
            'frequency' => 'monthly',
            'is_active' => false,
            'notes' => 'Ended Oct 2025',
        ]);

        // --- Calendar events ---
        // Family dinner - weekly recurring
        $familyDinner = Event::factory()->create([
            'user_id' => $admin->id,
            'title' => 'Family Dinner',
            'description' => 'Weekly family dinner together',
            'starts_at' => $today->copy()->next(Carbon::FRIDAY)->setHour(18),
            'ends_at' => $today->copy()->next(Carbon::FRIDAY)->setHour(20),
            'rrule' => 'FREQ=WEEKLY;BYDAY=FR',
        ]);
        $familyDinner->familyMembers()->attach(collect($allMembers)->pluck('id'));

        // Soccer practice - weekly
        $soccer = Event::factory()->create([
            'user_id' => $admin->id,
            'title' => 'Soccer Practice',
            'starts_at' => $today->copy()->next(Carbon::WEDNESDAY)->setHour(16),
            'ends_at' => $today->copy()->next(Carbon::WEDNESDAY)->setHour(17)->setMinute(30),
            'rrule' => 'FREQ=WEEKLY;BYDAY=WE',
        ]);
        $soccer->familyMembers()->attach([$teenMember->id, $childMember->id]);

        // Date night - biweekly
        $dateNight = Event::factory()->create([
            'user_id' => $admin->id,
            'title' => 'Date Night',
            'starts_at' => $today->copy()->next(Carbon::SATURDAY)->setHour(19),
            'ends_at' => $today->copy()->next(Carbon::SATURDAY)->setHour(22),
            'rrule' => 'FREQ=WEEKLY;INTERVAL=2;BYDAY=SA',
        ]);
        $dateNight->familyMembers()->attach([$adminMember->id, $spouseMember->id]);

        // One-off events
        Event::factory()->create([
            'user_id' => $admin->id,
            'title' => 'Dentist Appointment',
            'starts_at' => $today->copy()->addDays(5)->setHour(10),
            'ends_at' => $today->copy()->addDays(5)->setHour(11),
        ])->familyMembers()->attach([$adminMember->id]);

        Event::factory()->create([
            'user_id' => $admin->id,
            'title' => 'School Play',
            'description' => 'Spring musical performance',
            'starts_at' => $today->copy()->addDays(12)->setHour(18)->setMinute(30),
            'ends_at' => $today->copy()->addDays(12)->setHour(20)->setMinute(30),
        ])->familyMembers()->attach(collect($allMembers)->pluck('id'));

        Event::factory()->create([
            'user_id' => $admin->id,
            'title' => 'Oil Change',
            'starts_at' => $today->copy()->addDays(8)->setHour(9),
            'ends_at' => $today->copy()->addDays(8)->setHour(10),
        ]);

        // All-day events
        Event::factory()->allDay()->create([
            'user_id' => $admin->id,
            'title' => 'Spring Break',
            'starts_at' => $today->copy()->addDays(20)->startOfDay(),
            'ends_at' => $today->copy()->addDays(27)->endOfDay(),
        ])->familyMembers()->attach(collect($allMembers)->pluck('id'));

        Event::factory()->allDay()->create([
            'user_id' => $admin->id,
            'title' => 'Sam\'s Birthday',
            'starts_at' => $today->copy()->addDays(15)->startOfDay(),
            'ends_at' => $today->copy()->addDays(15)->endOfDay(),
        ])->familyMembers()->attach([$childMember->id]);

        // Past event
        Event::factory()->create([
            'user_id' => $admin->id,
            'title' => 'Parent-Teacher Conference',
            'starts_at' => $today->copy()->subDays(3)->setHour(15),
            'ends_at' => $today->copy()->subDays(3)->setHour(16),
        ])->familyMembers()->attach([$adminMember->id, $spouseMember->id]);

        // --- Lists ---

        // Grocery list (everyone can see) with mixed items
        $groceryList = FamilyList::factory()->grocery()->create([
            'user_id' => $admin->id,
            'name' => 'Weekly Groceries',
        ]);

        $groceryItems = ['Milk', 'Eggs', 'Bread', 'Chicken breast', 'Broccoli', 'Rice', 'Pasta', 'Tomato sauce', 'Bananas', 'Cheese'];
        $groceryQtys = ['1 gallon', '1 dozen', '1 loaf', '2 lbs', '2 heads', '1 bag', '2 boxes', '2 jars', '1 bunch', '1 block'];
        foreach ($groceryItems as $i => $item) {
            FamilyListItem::factory()->create([
                'family_list_id' => $groceryList->id,
                'name' => $item,
                'quantity' => $groceryQtys[$i],
                'is_completed' => $i < 3, // first 3 checked off
                'position' => $i,
            ]);
        }

        // To-do list (parents only)
        $todoList = FamilyList::factory()->todo()->parentsOnly()->create([
            'user_id' => $admin->id,
            'name' => 'Home Improvements',
        ]);

        $todoItems = ['Fix leaky faucet', 'Paint bedroom', 'Replace air filters', 'Organize garage', 'Install new doorbell'];
        foreach ($todoItems as $i => $item) {
            FamilyListItem::factory()->create([
                'family_list_id' => $todoList->id,
                'name' => $item,
                'quantity' => null,
                'notes' => $i === 0 ? 'Kitchen sink drips at night' : null,
                'is_completed' => $i === 2,
                'position' => $i,
            ]);
        }

        // Wishlist (specific members — the kids)
        $wishlist = FamilyList::factory()->wishlist()->specific()->create([
            'user_id' => $admin->id,
            'name' => 'Birthday Wishlist',
        ]);
        $wishlist->members()->attach([$teenMember->id, $childMember->id]);

        $wishItems = ['LEGO set', 'New headphones', 'Skateboard', 'Art supplies'];
        $wishNotes = ['The Star Wars one', 'Wireless, over-ear', null, 'Colored pencils + sketchbook'];
        foreach ($wishItems as $i => $item) {
            FamilyListItem::factory()->create([
                'family_list_id' => $wishlist->id,
                'name' => $item,
                'quantity' => null,
                'notes' => $wishNotes[$i],
                'position' => $i,
            ]);
        }

        // Shopping list (everyone)
        $shoppingList = FamilyList::factory()->shopping()->create([
            'user_id' => $admin->id,
            'name' => 'Back to School',
        ]);

        $schoolItems = ['Backpack', 'Notebooks', 'Pencils', 'Calculator', 'Lunch box', 'Water bottle'];
        $schoolQtys = ['1', '5', '1 pack', '1', '1', '2'];
        foreach ($schoolItems as $i => $item) {
            FamilyListItem::factory()->create([
                'family_list_id' => $shoppingList->id,
                'name' => $item,
                'quantity' => $schoolQtys[$i],
                'is_completed' => $i < 2,
                'position' => $i,
            ]);
        }

        // --- Recipes & Cookbooks ---
        $this->call([
            RecipeSeeder::class,
            CookbookSeeder::class,
        ]);

        // --- Chores ---
        $choreNames = ['Wash dishes', 'Take out trash', 'Vacuum living room', 'Feed the pets', 'Set the table'];
        $chores = collect($choreNames)->map(fn ($name) => Chore::factory()->create([
            'user_id' => $admin->id,
            'name' => $name,
        ]));

        // Assign chores to teen and child members on specific days
        $assignments = [
            [$chores[0], $teenMember, 1],   // Wash dishes - Mon
            [$chores[0], $childMember, 4],   // Wash dishes - Thu
            [$chores[1], $teenMember, 2],    // Take out trash - Tue
            [$chores[1], $teenMember, 5],    // Take out trash - Fri
            [$chores[2], $childMember, 3],   // Vacuum - Wed
            [$chores[3], $childMember, 0],   // Feed pets - Sun
            [$chores[3], $childMember, 6],   // Feed pets - Sat
            [$chores[4], $teenMember, 0],    // Set table - Sun
            [$chores[4], $childMember, 3],   // Set table - Wed
        ];

        foreach ($assignments as [$chore, $member, $day]) {
            ChoreAssignment::factory()->create([
                'chore_id' => $chore->id,
                'family_member_id' => $member->id,
                'day_of_week' => $day,
            ]);
        }

        // --- Contacts ---
        Contact::factory()->create([
            'user_id' => $admin->id,
            'first_name' => 'Dr. Sarah',
            'last_name' => 'Chen',
            'phone' => '(555) 123-4567',
            'email' => 'dr.chen@example.com',
            'notes' => 'Family pediatrician',
        ]);

        Contact::factory()->withBirthday()->create([
            'user_id' => $admin->id,
            'first_name' => 'Mike',
            'last_name' => 'Johnson',
            'phone' => '(555) 987-6543',
            'date_of_birth' => '1988-07-15',
            'notes' => 'College friend',
        ]);

        Contact::factory()->withBirthday()->create([
            'user_id' => $admin->id,
            'first_name' => 'Grandma',
            'last_name' => 'Doe',
            'phone' => '(555) 456-7890',
            'date_of_birth' => '1950-12-03',
            'address_line_1' => '42 Maple Lane',
            'city' => 'Springfield',
            'state' => 'IL',
            'zip' => '62701',
        ]);

        Contact::factory()->create([
            'user_id' => $admin->id,
            'first_name' => 'Coach',
            'last_name' => 'Williams',
            'phone' => '(555) 321-0987',
            'email' => 'coach.w@example.com',
            'notes' => 'Soccer coach for Alex & Sam',
        ]);

        // --- Meal plan entries (7 days of breakfast + dinner) ---
        $recipes = Recipe::where('user_id', $admin->id)->take(4)->get();

        for ($d = 0; $d < 7; $d++) {
            $date = $today->copy()->startOfWeek()->addDays($d)->format('Y-m-d');

            // Breakfast
            MealPlanEntry::factory()->breakfast()->create([
                'user_id' => $admin->id,
                'date' => $date,
                'name' => fake()->randomElement(['Oatmeal', 'Scrambled eggs', 'Pancakes', 'Smoothie bowl', 'Toast & fruit', 'Cereal', 'Yogurt parfait']),
            ]);

            // Dinner (some linked to recipes)
            $recipe = $recipes->isNotEmpty() && $d < $recipes->count() ? $recipes[$d] : null;
            MealPlanEntry::factory()->dinner()->create([
                'user_id' => $admin->id,
                'date' => $date,
                'recipe_id' => $recipe?->id,
                'name' => $recipe?->name ?? fake()->randomElement(['Grilled chicken', 'Pasta night', 'Stir-fry', 'Soup & salad', 'Taco Tuesday', 'Pizza', 'Burgers']),
            ]);
        }
    }
}
