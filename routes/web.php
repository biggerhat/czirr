<?php

use App\Http\Controllers\BillController;
use App\Http\Controllers\BudgetCategoryController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\ChoreAssignmentController;
use App\Http\Controllers\ChoreController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CookbookController;
use App\Http\Controllers\CuisineController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\EventTypeController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\FamilyListController;
use App\Http\Controllers\FamilyListItemController;
use App\Http\Controllers\FamilyMemberController;
use App\Http\Controllers\IncomeController;
use App\Http\Controllers\MealPlanController;
use App\Http\Controllers\RecipeController;
use App\Http\Controllers\RecipeTagController;
use App\Http\Controllers\RoleController;
use Illuminate\Support\Facades\Route;

Route::get('/offline', fn () => view('offline'));

Route::get('/', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified', 'family'])->name('dashboard');

Route::get('dashboard', fn () => redirect('/'));

Route::middleware(['auth', 'verified', 'family'])->group(function () {
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

    // Recipes
    Route::get('recipes', [RecipeController::class, 'index'])->name('recipes.index');
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
    Route::post('meal-plans/generate-grocery-list', [MealPlanController::class, 'generateGroceryList'])->middleware('permission:meal-plans.generate-grocery-list');

    // Chores
    Route::get('chores', [ChoreController::class, 'index'])->name('chores.index');
    Route::post('chores', [ChoreController::class, 'store'])->middleware('permission:chores.create');
    Route::put('chores/{chore}', [ChoreController::class, 'update'])->middleware('permission:chores.edit');
    Route::delete('chores/{chore}', [ChoreController::class, 'destroy'])->middleware('permission:chores.delete');
    Route::post('chore-assignments/toggle', [ChoreAssignmentController::class, 'toggle'])->middleware('permission:chores.assign');

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
