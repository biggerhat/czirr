<?php

namespace App\Enums;

enum DefaultRole: string
{
    case Admin = 'admin';
    case Parent = 'parent';
    case Child = 'child';

    /**
     * Get the permissions for this default role.
     *
     * @return array<string>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Admin => Permission::values(),
            self::Parent => array_values(array_filter(
                Permission::values(),
                fn (string $p) => $p !== Permission::RolesManage->value,
            )),
            self::Child => [
                // View-only for most modules
                Permission::EventsView->value,
                Permission::RecipesView->value,
                Permission::CookbooksView->value,
                Permission::MealPlansView->value,
                Permission::ChoresView->value,
                Permission::ContactsView->value,

                // Full list access (matches current child behavior)
                Permission::ListsView->value,
                Permission::ListsCreate->value,
                Permission::ListsEdit->value,
                Permission::ListsDelete->value,
            ],
        };
    }

    /**
     * Get all default role names.
     *
     * @return array<string>
     */
    public static function names(): array
    {
        return array_column(self::cases(), 'value');
    }
}
