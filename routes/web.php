<?php

use App\Http\Controllers\BillController;
use App\Http\Controllers\BoardController;
use App\Http\Controllers\BonusObjectiveController;
use App\Http\Controllers\BudgetCategoryController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CalendarFeedController;
use App\Http\Controllers\ChoreAssignmentController;
use App\Http\Controllers\ChoreCompletionController;
use App\Http\Controllers\ChoreController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CookbookController;
use App\Http\Controllers\CuisineController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\EventTypeController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ExpenseImportController;
use App\Http\Controllers\FamilyInviteController;
use App\Http\Controllers\FamilyListController;
use App\Http\Controllers\FamilyListItemController;
use App\Http\Controllers\FamilyMemberController;
use App\Http\Controllers\IncomeController;
use App\Http\Controllers\MealPlanController;
use App\Http\Controllers\MyDayController;
use App\Http\Controllers\PantryController;
use App\Http\Controllers\RecipeController;
use App\Http\Controllers\RecipeImportController;
use App\Http\Controllers\RecipeRatingController;
use App\Http\Controllers\RecipeTagController;
use App\Http\Controllers\RewardController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SavingsController;
use App\Http\Controllers\ScoreboardController;
use App\Http\Controllers\ScoreGameController;
use App\Http\Controllers\YahtzeeGameController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('calendar/feed/{token}.ics', [CalendarFeedController::class, 'show'])
    ->where('token', '[A-Za-z0-9]{32,64}')
    ->middleware('throttle:60,1')
    ->name('calendar.feed');

Route::get('invite/{token}', [FamilyInviteController::class, 'show'])
    ->where('token', '[A-Za-z0-9]{48}')->middleware('throttle:30,1')->name('invite.show');
Route::post('invite/{token}/accept', [FamilyInviteController::class, 'accept'])
    ->where('token', '[A-Za-z0-9]{48}')->middleware(['auth', 'throttle:10,1'])->name('invite.accept');

Route::get('/offline', fn () => view('offline'));

Route::get('/', function () {
    if (auth()->check()) {
        return redirect('/dashboard');
    }

    return Inertia::render('Welcome', [
        'canRegister' => Route::has('register'),
    ]);
})->name('home');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified', 'family'])->name('dashboard');

Route::middleware(['auth', 'verified', 'family', 'throttle:family'])->group(function () {
    // Calendar (no specific permission — all authenticated family members can view)
    Route::get('calendar', [CalendarController::class, 'index'])->name('calendar.index');

    // Events
    Route::get('events', [EventController::class, 'index'])->name('events.index');
    Route::post('events', [EventController::class, 'store'])->middleware('permission:events.create')->name('events.store');
    Route::put('events/{event}', [EventController::class, 'update'])->middleware('permission:events.edit')->name('events.update');
    Route::delete('events/{event}', [EventController::class, 'destroy'])->middleware('permission:events.delete')->name('events.destroy');

    // Event Types
    Route::post('event-types', [EventTypeController::class, 'store'])->middleware('permission:events.create');

    // Lists
    Route::get('lists', [FamilyListController::class, 'index'])->name('lists.index');
    Route::get('lists/{familyList}', [FamilyListController::class, 'show'])->name('lists.show');
    Route::post('lists', [FamilyListController::class, 'store'])->middleware('permission:lists.create');
    Route::put('lists/{familyList}', [FamilyListController::class, 'update'])->middleware('permission:lists.edit');
    Route::delete('lists/{familyList}', [FamilyListController::class, 'destroy'])->middleware('permission:lists.delete');
    Route::patch('lists/{familyList}/pin', [FamilyListController::class, 'togglePin'])->middleware('permission:lists.edit');

    Route::post('lists/{familyList}/items', [FamilyListItemController::class, 'store'])->middleware('permission:lists.edit');
    Route::delete('lists/{familyList}/items/completed', [FamilyListItemController::class, 'clearCompleted'])->middleware('permission:lists.edit');
    Route::put('list-items/{familyListItem}', [FamilyListItemController::class, 'update'])->middleware('permission:lists.edit');
    Route::patch('list-items/{familyListItem}/toggle', [FamilyListItemController::class, 'toggleComplete'])->middleware('permission:lists.edit');
    Route::delete('list-items/{familyListItem}', [FamilyListItemController::class, 'destroy'])->middleware('permission:lists.delete');

    // Family board
    Route::get('board', [BoardController::class, 'index'])->middleware('permission:lists.view')->name('board.index');
    Route::post('board', [BoardController::class, 'store'])->middleware(['permission:lists.create', 'throttle:20,1'])->name('board.store');
    Route::patch('board/{post}/pin', [BoardController::class, 'togglePin'])->middleware('permission:family.edit')->name('board.pin');
    Route::delete('board/{post}', [BoardController::class, 'destroy'])->middleware('permission:lists.view')->name('board.destroy');

    // Recipes
    Route::get('recipes', [RecipeController::class, 'index'])->name('recipes.index');
    Route::post('recipes/import', RecipeImportController::class)->middleware(['permission:recipes.create', 'throttle:20,1'])->name('recipes.import');
    Route::put('recipes/{recipe}/rating', [RecipeRatingController::class, 'update'])->name('recipes.rating.update');
    Route::delete('recipes/{recipe}/rating', [RecipeRatingController::class, 'destroy'])->name('recipes.rating.destroy');
    Route::get('recipes/create', [RecipeController::class, 'create'])->middleware('permission:recipes.create')->name('recipes.create');
    Route::get('recipes/{recipe}/edit', [RecipeController::class, 'edit'])->middleware('permission:recipes.edit')->name('recipes.edit');
    Route::get('recipes/{recipe}', [RecipeController::class, 'show'])->name('recipes.show');
    Route::post('recipes', [RecipeController::class, 'store'])->middleware('permission:recipes.create');
    Route::put('recipes/{recipe}', [RecipeController::class, 'update'])->middleware('permission:recipes.edit');
    Route::delete('recipes/{recipe}', [RecipeController::class, 'destroy'])->middleware('permission:recipes.delete');

    // Cuisines
    Route::post('cuisines', [CuisineController::class, 'store'])->middleware('permission:cuisines.create');
    Route::delete('cuisines/{cuisine}', [CuisineController::class, 'destroy'])->middleware('permission:cuisines.delete');

    // Recipe Tags
    Route::post('recipe-tags', [RecipeTagController::class, 'store'])->middleware('permission:recipe-tags.create');
    Route::delete('recipe-tags/{recipeTag}', [RecipeTagController::class, 'destroy'])->middleware('permission:recipe-tags.delete');

    // Cookbooks
    Route::get('cookbooks', [CookbookController::class, 'index'])->name('cookbooks.index');
    Route::get('cookbooks/{cookbook}', [CookbookController::class, 'show'])->name('cookbooks.show');
    Route::post('cookbooks', [CookbookController::class, 'store'])->middleware('permission:cookbooks.create');
    Route::put('cookbooks/{cookbook}', [CookbookController::class, 'update'])->middleware('permission:cookbooks.edit');
    Route::delete('cookbooks/{cookbook}', [CookbookController::class, 'destroy'])->middleware('permission:cookbooks.delete');
    Route::post('cookbooks/{cookbook}/recipes', [CookbookController::class, 'addRecipe'])->middleware('permission:cookbooks.edit');
    Route::delete('cookbooks/{cookbook}/recipes/{recipe}', [CookbookController::class, 'removeRecipe'])->middleware('permission:cookbooks.edit');

    // Meal Plans
    Route::get('meal-plans', [MealPlanController::class, 'index'])->name('meal-plans.index');
    Route::post('meal-plan-entries', [MealPlanController::class, 'store'])->middleware('permission:meal-plans.create');
    Route::put('meal-plan-entries/{mealPlanEntry}', [MealPlanController::class, 'update'])->middleware('permission:meal-plans.edit');
    Route::delete('meal-plan-entries/{mealPlanEntry}', [MealPlanController::class, 'destroy'])->middleware('permission:meal-plans.delete');
    Route::get('meal-plans/suggestions', [MealPlanController::class, 'suggestions'])->middleware('permission:meal-plans.view')->name('meal-plans.suggestions');
    Route::post('meal-plans/copy-previous-week', [MealPlanController::class, 'copyPreviousWeek'])->middleware('permission:meal-plans.create')->name('meal-plans.copy-week');
    Route::post('meal-plans/generate-grocery-list', [MealPlanController::class, 'generateGroceryList'])->middleware('permission:meal-plans.generate-grocery-list');

    // Pantry staples (skipped when generating grocery lists)
    Route::get('pantry', [PantryController::class, 'index'])->middleware('permission:meal-plans.view')->name('pantry.index');
    Route::post('pantry', [PantryController::class, 'store'])->middleware('permission:meal-plans.edit')->name('pantry.store');
    Route::delete('pantry/{pantryItem}', [PantryController::class, 'destroy'])->middleware('permission:meal-plans.edit')->name('pantry.destroy');

    // Chores
    Route::get('chores', [ChoreController::class, 'index'])->name('chores.index');
    Route::post('chores', [ChoreController::class, 'store'])->middleware('permission:chores.create');
    Route::put('chores/{chore}', [ChoreController::class, 'update'])->middleware('permission:chores.edit');
    Route::delete('chores/{chore}', [ChoreController::class, 'destroy'])->middleware('permission:chores.delete');
    Route::post('chore-assignments/toggle', [ChoreAssignmentController::class, 'toggle'])->middleware('permission:chores.assign');
    Route::post('chore-completions/toggle', [ChoreCompletionController::class, 'toggle'])->middleware('permission:chores.view');
    Route::patch('chore-completions/{completion}', [ChoreCompletionController::class, 'resolve'])->middleware('permission:chores.edit')->name('chore-completions.resolve');

    // Bonus Objectives
    Route::post('bonus-objectives', [BonusObjectiveController::class, 'store'])->middleware('permission:chores.create');
    Route::put('bonus-objectives/{bonusObjective}', [BonusObjectiveController::class, 'update'])->middleware('permission:chores.edit');
    Route::delete('bonus-objectives/{bonusObjective}', [BonusObjectiveController::class, 'destroy'])->middleware('permission:chores.delete');
    Route::post('bonus-objectives/{bonusObjective}/claim', [BonusObjectiveController::class, 'claim'])->middleware('permission:chores.view');

    // Savings ("kid bank"): everyone with chores.view sees their own; family.edit manages
    Route::get('savings', [SavingsController::class, 'index'])->middleware('permission:chores.view')->name('savings.index');
    Route::post('savings', [SavingsController::class, 'store'])->middleware(['permission:family.edit', 'throttle:60,1'])->name('savings.store');
    Route::delete('savings/{entry}', [SavingsController::class, 'destroy'])->middleware('permission:family.edit')->name('savings.destroy');

    // Scoreboard
    Route::get('my-day', [MyDayController::class, 'index'])->middleware('permission:chores.view')->name('my-day.index');

    // Rewards
    Route::get('rewards', [RewardController::class, 'index'])->middleware('permission:chores.view')->name('rewards.index');
    Route::post('rewards', [RewardController::class, 'store'])->middleware('permission:chores.edit')->name('rewards.store');
    Route::put('rewards/{reward}', [RewardController::class, 'update'])->middleware('permission:chores.edit')->name('rewards.update');
    Route::delete('rewards/{reward}', [RewardController::class, 'destroy'])->middleware('permission:chores.edit')->name('rewards.destroy');
    Route::post('rewards/{reward}/redeem', [RewardController::class, 'redeem'])->middleware('permission:chores.view')->name('rewards.redeem');
    Route::patch('reward-redemptions/{redemption}', [RewardController::class, 'resolve'])->middleware('permission:chores.edit')->name('rewards.resolve');

    Route::get('scoreboard', [ScoreboardController::class, 'index'])->middleware('permission:chores.view')->name('scoreboard.index');

    // Yahtzee
    Route::get('yahtzee', [YahtzeeGameController::class, 'index'])->name('yahtzee.index');
    Route::post('yahtzee', [YahtzeeGameController::class, 'store'])->name('yahtzee.store');
    Route::get('yahtzee/{yahtzeeGame}', [YahtzeeGameController::class, 'show'])->name('yahtzee.show');
    Route::post('yahtzee/{yahtzeeGame}/roll', [YahtzeeGameController::class, 'roll'])->name('yahtzee.roll');
    Route::patch('yahtzee/{yahtzeeGame}/hold', [YahtzeeGameController::class, 'hold'])->name('yahtzee.hold');
    Route::post('yahtzee/{yahtzeeGame}/score', [YahtzeeGameController::class, 'score'])->name('yahtzee.score');
    Route::delete('yahtzee/{yahtzeeGame}', [YahtzeeGameController::class, 'destroy'])->name('yahtzee.destroy');

    // Game night (generic scorekeeper + Farkle)
    Route::get('games', [ScoreGameController::class, 'index'])->middleware('permission:lists.view')->name('games.index');
    Route::post('games', [ScoreGameController::class, 'store'])->middleware(['permission:lists.create', 'throttle:60,1'])->name('games.store');
    Route::get('games/{scoreGame}', [ScoreGameController::class, 'show'])->middleware('permission:lists.view')->name('games.show');
    Route::post('games/{scoreGame}/rounds', [ScoreGameController::class, 'addRound'])->middleware('permission:lists.create')->name('games.rounds.store');
    Route::delete('games/{scoreGame}/rounds', [ScoreGameController::class, 'undoRound'])->middleware('permission:lists.create')->name('games.rounds.undo');
    Route::post('games/{scoreGame}/finish', [ScoreGameController::class, 'finish'])->middleware('permission:lists.create')->name('games.finish');
    Route::delete('games/{scoreGame}', [ScoreGameController::class, 'destroy'])->middleware('permission:lists.delete')->name('games.destroy');

    // Contacts
    Route::get('contacts', [ContactController::class, 'index'])->name('contacts.index');
    Route::post('contacts', [ContactController::class, 'store'])->middleware('permission:contacts.create');
    Route::put('contacts/{contact}', [ContactController::class, 'update'])->middleware('permission:contacts.edit');
    Route::delete('contacts/{contact}', [ContactController::class, 'destroy'])->middleware('permission:contacts.delete');

    // Budgeting
    Route::get('budgeting', [BudgetController::class, 'index'])->middleware('permission:budgeting.view')->name('budgeting.index');

    Route::post('bills', [BillController::class, 'store'])->middleware('permission:budgeting.create');
    Route::put('bills/{bill}', [BillController::class, 'update'])->middleware('permission:budgeting.edit');
    Route::delete('bills/{bill}', [BillController::class, 'destroy'])->middleware('permission:budgeting.delete');

    Route::post('expenses/import', ExpenseImportController::class)->middleware(['permission:budgeting.create', 'throttle:10,1'])->name('expenses.import');
    Route::post('expenses', [ExpenseController::class, 'store'])->middleware('permission:budgeting.create');
    Route::delete('expenses/{expense}', [ExpenseController::class, 'destroy'])->middleware('permission:budgeting.delete');

    Route::post('incomes', [IncomeController::class, 'store'])->middleware('permission:budgeting.create');
    Route::put('incomes/{income}', [IncomeController::class, 'update'])->middleware('permission:budgeting.edit');
    Route::delete('incomes/{income}', [IncomeController::class, 'destroy'])->middleware('permission:budgeting.delete');

    Route::post('budget-categories', [BudgetCategoryController::class, 'store'])->middleware('permission:budgeting.create');
    Route::put('budget-categories/{budgetCategory}', [BudgetCategoryController::class, 'update'])->middleware('permission:budgeting.edit');
    Route::delete('budget-categories/{budgetCategory}', [BudgetCategoryController::class, 'destroy'])->middleware('permission:budgeting.delete');

    // Family
    Route::get('family', [FamilyMemberController::class, 'index'])->middleware('permission:family.view')->name('family.index');
    Route::post('family/invites', [FamilyInviteController::class, 'store'])->middleware('permission:family.create')->name('invite.store');
    Route::post('family', [FamilyMemberController::class, 'store'])->middleware('permission:family.create');
    Route::put('family/{familyMember}', [FamilyMemberController::class, 'update'])->middleware('permission:family.edit');
    Route::delete('family/{familyMember}', [FamilyMemberController::class, 'destroy'])->middleware('permission:family.delete');

    // Role management
    Route::middleware('permission:roles.manage')->group(function () {
        Route::get('family/roles', [RoleController::class, 'index'])->name('roles.index');
        Route::post('family/roles', [RoleController::class, 'store'])->name('roles.store');
        Route::put('family/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        Route::delete('family/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
    });

    Route::put('family/{familyMember}/role', [FamilyMemberController::class, 'updateRole'])->middleware('permission:roles.manage');
});

require __DIR__.'/settings.php';
