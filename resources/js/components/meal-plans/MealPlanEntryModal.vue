<script setup lang="ts">
import { Search } from 'lucide-vue-next';
import { ref, computed, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Sheet,
    SheetContent,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import type { CustomMeal, MealPlanEntry, MealType } from '@/types/meal-plans';
import { MEAL_TYPE_LABELS, MEAL_TYPES } from '@/types/meal-plans';
import type { Recipe } from '@/types/recipes';

const props = defineProps<{
    entry: MealPlanEntry | null;
    open: boolean;
    date?: string;
    mealType?: MealType;
    recipes: Pick<Recipe, 'id' | 'name'>[];
    customMeals: CustomMeal[];
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    saved: [];
}>();

const isEditing = computed(() => !!props.entry);
const isSaving = ref(false);
const errors = ref<Record<string, string[]>>({});

// Form fields
const formDate = ref('');
const formMealType = ref<MealType>('dinner');
const recipeId = ref<number | null>(null);
const name = ref('');
const description = ref('');
const useRecipe = ref(false);
const recipeSearch = ref('');
const customSearch = ref('');

const filteredRecipes = computed(() => {
    const q = recipeSearch.value.trim().toLowerCase();
    if (!q) return props.recipes;
    return props.recipes.filter(r => r.name.toLowerCase().includes(q));
});

const filteredCustomMeals = computed(() => {
    const q = customSearch.value.trim().toLowerCase();
    if (!q) return props.customMeals;
    return props.customMeals.filter(m => m.name.toLowerCase().includes(q));
});

watch(() => props.open, (open) => {
    if (!open) return;
    errors.value = {};
    recipeSearch.value = '';
    customSearch.value = '';

    if (props.entry) {
        formDate.value = props.entry.date;
        formMealType.value = props.entry.meal_type;
        recipeId.value = props.entry.recipe_id;
        name.value = props.entry.name;
        description.value = props.entry.description ?? '';
        useRecipe.value = !!props.entry.recipe_id;
    } else {
        formDate.value = props.date ?? '';
        formMealType.value = props.mealType ?? 'dinner';
        recipeId.value = null;
        name.value = '';
        description.value = '';
        useRecipe.value = false;
    }
});

function selectRecipe(recipe: Pick<Recipe, 'id' | 'name'>) {
    recipeId.value = recipe.id;
    name.value = recipe.name;
    recipeSearch.value = '';
}

function selectCustomMeal(meal: CustomMeal) {
    name.value = meal.name;
    description.value = meal.description ?? '';
    customSearch.value = '';
}

function clearRecipe() {
    recipeId.value = null;
    name.value = '';
}

async function save() {
    isSaving.value = true;
    errors.value = {};

    const body = {
        date: formDate.value,
        meal_type: formMealType.value,
        recipe_id: useRecipe.value ? recipeId.value : null,
        name: name.value,
        description: description.value || null,
    };

    try {
        const url = isEditing.value
            ? `/meal-plan-entries/${props.entry!.id}`
            : '/meal-plan-entries';
        const method = isEditing.value ? 'PUT' : 'POST';

        const response = await fetch(url, {
            method,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': decodeURIComponent(
                    document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
                ),
            },
            body: JSON.stringify(body),
        });

        if (response.ok) {
            emit('saved');
            emit('update:open', false);
        } else if (response.status === 422) {
            const data = await response.json();
            errors.value = data.errors ?? {};
        } else {
            errors.value = { name: [`Save failed (${response.status}). Please try again.`] };
        }
    } finally {
        isSaving.value = false;
    }
}
</script>

<template>
    <Sheet :open="open" @update:open="$emit('update:open', $event)">
        <SheetContent side="right" class="flex flex-col overflow-y-auto">
            <SheetHeader>
                <SheetTitle>{{ isEditing ? 'Edit Meal' : 'Add Meal' }}</SheetTitle>
            </SheetHeader>

            <form @submit.prevent="save" class="flex flex-1 flex-col">
                <div class="space-y-4 px-4 flex-1">
                    <!-- Date -->
                    <div class="space-y-2">
                        <Label for="entry-date">Date</Label>
                        <Input id="entry-date" v-model="formDate" type="date" required />
                        <p v-if="errors.date" class="text-sm text-destructive">{{ errors.date[0] }}</p>
                    </div>

                    <!-- Meal type -->
                    <div class="space-y-2">
                        <Label>Meal</Label>
                        <Select v-model="formMealType">
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="mt in MEAL_TYPES" :key="mt" :value="mt">
                                    {{ MEAL_TYPE_LABELS[mt] }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="errors.meal_type" class="text-sm text-destructive">{{ errors.meal_type[0] }}</p>
                    </div>

                    <!-- Source toggle -->
                    <div class="flex gap-2">
                        <Button
                            type="button"
                            size="sm"
                            :variant="!useRecipe ? 'default' : 'outline'"
                            @click="useRecipe = false; clearRecipe()"
                        >
                            Custom
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            :variant="useRecipe ? 'default' : 'outline'"
                            @click="useRecipe = true"
                        >
                            From Recipe
                        </Button>
                    </div>

                    <!-- Recipe picker -->
                    <template v-if="useRecipe">
                        <div class="space-y-2">
                            <Label>Recipe</Label>
                            <div v-if="recipeId" class="flex items-center gap-2">
                                <Badge variant="secondary" class="text-sm">{{ name }}</Badge>
                                <Button type="button" variant="ghost" size="sm" class="h-6 px-2 text-xs" @click="clearRecipe">
                                    Change
                                </Button>
                            </div>
                            <template v-else>
                                <div class="relative">
                                    <Search class="absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        v-model="recipeSearch"
                                        placeholder="Search recipes..."
                                        class="pl-9"
                                    />
                                </div>
                                <div class="max-h-40 overflow-y-auto rounded-md border">
                                    <button
                                        v-for="recipe in filteredRecipes"
                                        :key="recipe.id"
                                        type="button"
                                        class="w-full text-left px-3 py-2 text-sm hover:bg-accent transition-colors"
                                        @click="selectRecipe(recipe)"
                                    >
                                        {{ recipe.name }}
                                    </button>
                                    <div v-if="filteredRecipes.length === 0" class="px-3 py-2 text-sm text-muted-foreground">
                                        No recipes found.
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>

                    <!-- Custom meal -->
                    <template v-if="!useRecipe">
                        <!-- Previous meals quick-pick -->
                        <div v-if="customMeals.length > 0" class="space-y-1.5">
                            <Label>Previous meals</Label>
                            <div class="relative">
                                <Search class="absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    v-model="customSearch"
                                    placeholder="Search previous meals..."
                                    class="pl-9 h-8 text-sm"
                                />
                            </div>
                            <div class="max-h-28 overflow-y-auto rounded-md border">
                                <button
                                    v-for="(meal, i) in filteredCustomMeals"
                                    :key="i"
                                    type="button"
                                    class="w-full text-left px-3 py-1.5 text-sm hover:bg-accent transition-colors flex items-baseline justify-between gap-2"
                                    @click="selectCustomMeal(meal)"
                                >
                                    <span class="truncate">{{ meal.name }}</span>
                                    <span v-if="meal.description" class="text-xs text-muted-foreground truncate shrink-0 max-w-[40%]">
                                        {{ meal.description }}
                                    </span>
                                </button>
                                <div v-if="filteredCustomMeals.length === 0" class="px-3 py-1.5 text-sm text-muted-foreground">
                                    No matches.
                                </div>
                            </div>
                        </div>

                        <!-- Name input (always visible, pre-filled if picked) -->
                        <div class="space-y-2">
                            <Label for="entry-name">Name</Label>
                            <Input
                                id="entry-name"
                                v-model="name"
                                placeholder="e.g. Pasta night"
                                required
                                autocomplete="off"
                            />
                            <p v-if="errors.name" class="text-sm text-destructive">{{ errors.name[0] }}</p>
                        </div>
                    </template>

                    <!-- Description (always available) -->
                    <div class="space-y-2">
                        <Label for="entry-description">Description</Label>
                        <Textarea id="entry-description" v-model="description" placeholder="Optional notes" />
                        <p v-if="errors.description" class="text-sm text-destructive">{{ errors.description[0] }}</p>
                    </div>
                </div>

                <SheetFooter>
                    <div class="flex gap-2 w-full">
                        <Button type="button" variant="outline" class="flex-1" @click="$emit('update:open', false)">
                            Cancel
                        </Button>
                        <Button type="submit" class="flex-1" :disabled="isSaving || (!name && useRecipe && !recipeId)">
                            {{ isSaving ? 'Saving...' : (isEditing ? 'Update' : 'Add') }}
                        </Button>
                    </div>
                </SheetFooter>
            </form>
        </SheetContent>
    </Sheet>
</template>
