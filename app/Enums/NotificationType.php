<?php

namespace App\Enums;

enum NotificationType: string
{
    case BillsDue = 'bills_due';
    case ChoreReminders = 'chore_reminders';
    case EventReminders = 'event_reminders';
    case MealPlan = 'meal_plan';
    case ListItemAdded = 'list_item_added';
    case BirthdayReminders = 'birthday_reminders';

    public function label(): string
    {
        return match ($this) {
            self::BillsDue => 'Bills Due',
            self::ChoreReminders => 'Chore Reminders',
            self::EventReminders => 'Event Reminders',
            self::MealPlan => 'Meal Plan',
            self::ListItemAdded => 'List Item Added',
            self::BirthdayReminders => 'Birthday Reminders',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::BillsDue => 'Get notified when bills are due today',
            self::ChoreReminders => 'Daily reminders for assigned chores',
            self::EventReminders => 'Reminders 15 minutes before events start',
            self::MealPlan => 'Daily meal plan summary',
            self::ListItemAdded => 'When someone adds an item to a shared list',
            self::BirthdayReminders => 'Birthday reminders for your contacts',
        };
    }

    public function requiredPermission(): Permission
    {
        return match ($this) {
            self::BillsDue => Permission::BudgetingView,
            self::ChoreReminders => Permission::ChoresView,
            self::EventReminders => Permission::EventsView,
            self::MealPlan => Permission::MealPlansView,
            self::ListItemAdded => Permission::ListsView,
            self::BirthdayReminders => Permission::ContactsView,
        };
    }
}
