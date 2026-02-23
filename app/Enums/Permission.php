<?php

namespace App\Enums;

enum Permission: string
{
    // Events
    case EventsView = 'events.view';
    case EventsCreate = 'events.create';
    case EventsEdit = 'events.edit';
    case EventsDelete = 'events.delete';

    // Lists
    case ListsView = 'lists.view';
    case ListsCreate = 'lists.create';
    case ListsEdit = 'lists.edit';
    case ListsDelete = 'lists.delete';

    // Recipes
    case RecipesView = 'recipes.view';
    case RecipesCreate = 'recipes.create';
    case RecipesEdit = 'recipes.edit';
    case RecipesDelete = 'recipes.delete';

    // Cookbooks
    case CookbooksView = 'cookbooks.view';
    case CookbooksCreate = 'cookbooks.create';
    case CookbooksEdit = 'cookbooks.edit';
    case CookbooksDelete = 'cookbooks.delete';

    // Cuisines
    case CuisinesCreate = 'cuisines.create';
    case CuisinesDelete = 'cuisines.delete';

    // Recipe Tags
    case RecipeTagsCreate = 'recipe-tags.create';
    case RecipeTagsDelete = 'recipe-tags.delete';

    // Meal Plans
    case MealPlansView = 'meal-plans.view';
    case MealPlansCreate = 'meal-plans.create';
    case MealPlansEdit = 'meal-plans.edit';
    case MealPlansDelete = 'meal-plans.delete';
    case MealPlansGenerateGroceryList = 'meal-plans.generate-grocery-list';

    // Chores
    case ChoresView = 'chores.view';
    case ChoresCreate = 'chores.create';
    case ChoresEdit = 'chores.edit';
    case ChoresDelete = 'chores.delete';
    case ChoresAssign = 'chores.assign';

    // Contacts
    case ContactsView = 'contacts.view';
    case ContactsCreate = 'contacts.create';
    case ContactsEdit = 'contacts.edit';
    case ContactsDelete = 'contacts.delete';

    // Budgeting
    case BudgetingView = 'budgeting.view';
    case BudgetingCreate = 'budgeting.create';
    case BudgetingEdit = 'budgeting.edit';
    case BudgetingDelete = 'budgeting.delete';

    // Family
    case FamilyView = 'family.view';
    case FamilyCreate = 'family.create';
    case FamilyEdit = 'family.edit';
    case FamilyDelete = 'family.delete';

    // Roles
    case RolesManage = 'roles.manage';

    /**
     * Get all permission values as an array of strings.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Group permissions by module for UI display.
     */
    public static function grouped(): array
    {
        $groups = [];

        foreach (self::cases() as $case) {
            [$module, $action] = explode('.', $case->value, 2);
            $groups[$module][] = [
                'value' => $case->value,
                'action' => $action,
            ];
        }

        return $groups;
    }
}
