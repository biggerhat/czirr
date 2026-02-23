import type { FamilyMember } from './calendar';

export type RecipeDifficulty = 'easy' | 'medium' | 'hard';
export type CookbookVisibility = 'everyone' | 'parents' | 'children' | 'specific';

export type Cuisine = {
    id: number;
    user_id: number | null;
    name: string;
};

export type RecipeTag = {
    id: number;
    user_id: number | null;
    name: string;
};

export type RecipeIngredient = {
    name: string;
    quantity: string | null;
    unit: string | null;
    notes: string | null;
};

export type Recipe = {
    id: number;
    user_id: number;
    name: string;
    description: string | null;
    ingredients: RecipeIngredient[];
    instructions: string;
    prep_time: number;
    cook_time: number;
    servings: number;
    image_url: string | null;
    source_url: string | null;
    cuisine_id: number | null;
    cuisine?: Cuisine | null;
    difficulty: RecipeDifficulty;
    tags?: RecipeTag[];
    notes: string | null;
    cookbooks?: Cookbook[];
    cookbooks_count?: number;
    created_at: string;
    updated_at: string;
};

export type Cookbook = {
    id: number;
    user_id: number;
    name: string;
    description: string | null;
    visibility: CookbookVisibility;
    recipes?: Recipe[];
    recipes_count?: number;
    members?: FamilyMember[];
    created_at: string;
    updated_at: string;
};

export const DIFFICULTY_LABELS: Record<RecipeDifficulty, string> = {
    easy: 'Easy',
    medium: 'Medium',
    hard: 'Hard',
};

export const DIFFICULTY_COLORS: Record<RecipeDifficulty, string> = {
    easy: 'text-green-600',
    medium: 'text-yellow-600',
    hard: 'text-red-600',
};

export const COOKBOOK_VISIBILITY_LABELS: Record<CookbookVisibility, string> = {
    everyone: 'Everyone',
    parents: 'Parents Only',
    children: 'Children Only',
    specific: 'Specific Members',
};
