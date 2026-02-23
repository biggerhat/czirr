import type { Recipe } from './recipes';

export type MealType = 'breakfast' | 'lunch' | 'dinner' | 'snack';

export type CustomMeal = {
    name: string;
    description: string | null;
};

export const MEAL_TYPE_LABELS: Record<MealType, string> = {
    breakfast: 'Breakfast',
    lunch: 'Lunch',
    dinner: 'Dinner',
    snack: 'Snack',
};

export const MEAL_TYPES: MealType[] = ['breakfast', 'lunch', 'dinner', 'snack'];

export type MealPlanEntry = {
    id: number;
    user_id: number;
    date: string;
    meal_type: MealType;
    recipe_id: number | null;
    recipe?: Pick<Recipe, 'id' | 'name' | 'description' | 'prep_time' | 'cook_time' | 'servings' | 'difficulty'> | null;
    name: string;
    description: string | null;
    created_at: string;
    updated_at: string;
};
