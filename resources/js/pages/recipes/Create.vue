<script setup lang="ts">
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { Plus, Trash2, X } from 'lucide-vue-next';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import type { BreadcrumbItem } from '@/types';
import type { Cuisine, RecipeTag, RecipeDifficulty } from '@/types/recipes';
import { DIFFICULTY_LABELS } from '@/types/recipes';

const props = defineProps<{
    cuisines: Cuisine[];
    tags: RecipeTag[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Recipes', href: '/recipes' },
    { title: 'New Recipe' },
];

type IngredientForm = { name: string; quantity: string; unit: string; notes: string };

const isSaving = ref(false);
const errors = ref<Record<string, string[]>>({});

const name = ref('');
const description = ref('');
const ingredients = ref<IngredientForm[]>([{ name: '', quantity: '', unit: '', notes: '' }]);
const instructions = ref('');
const prepTime = ref(0);
const cookTime = ref(0);
const servings = ref(4);
const imageUrl = ref('');
const sourceUrl = ref('');
const cuisineId = ref<string>('');
const difficulty = ref<RecipeDifficulty>('easy');
const selectedTagIds = ref<number[]>([]);
const notes = ref('');

// Inline-create state
const allCuisines = ref<Cuisine[]>([...props.cuisines]);
const allTags = ref<RecipeTag[]>([...props.tags]);
const newCuisineName = ref('');
const newTagName = ref('');
const isCreatingCuisine = ref(false);
const isCreatingTag = ref(false);
const showCuisineCreate = ref(false);
const showTagCreate = ref(false);

const selectedTags = computed(() =>
    allTags.value.filter(t => selectedTagIds.value.includes(t.id)),
);

function toggleTag(tagId: number) {
    const idx = selectedTagIds.value.indexOf(tagId);
    if (idx === -1) {
        selectedTagIds.value.push(tagId);
    } else {
        selectedTagIds.value.splice(idx, 1);
    }
}

function removeTag(tagId: number) {
    const idx = selectedTagIds.value.indexOf(tagId);
    if (idx !== -1) selectedTagIds.value.splice(idx, 1);
}

async function createCuisine() {
    if (!newCuisineName.value.trim()) return;
    isCreatingCuisine.value = true;

    try {
        const response = await fetch('/cuisines', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': decodeURIComponent(
                    document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
                ),
            },
            body: JSON.stringify({ name: newCuisineName.value.trim() }),
        });

        if (response.ok) {
            const cuisine: Cuisine = await response.json();
            allCuisines.value.push(cuisine);
            allCuisines.value.sort((a, b) => a.name.localeCompare(b.name));
            cuisineId.value = String(cuisine.id);
            newCuisineName.value = '';
            showCuisineCreate.value = false;
        }
    } finally {
        isCreatingCuisine.value = false;
    }
}

async function createTag() {
    if (!newTagName.value.trim()) return;
    isCreatingTag.value = true;

    try {
        const response = await fetch('/recipe-tags', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': decodeURIComponent(
                    document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
                ),
            },
            body: JSON.stringify({ name: newTagName.value.trim() }),
        });

        if (response.ok) {
            const tag: RecipeTag = await response.json();
            allTags.value.push(tag);
            allTags.value.sort((a, b) => a.name.localeCompare(b.name));
            selectedTagIds.value.push(tag.id);
            newTagName.value = '';
            showTagCreate.value = false;
        }
    } finally {
        isCreatingTag.value = false;
    }
}

function addIngredient() {
    ingredients.value.push({ name: '', quantity: '', unit: '', notes: '' });
}

function removeIngredient(index: number) {
    if (ingredients.value.length > 1) {
        ingredients.value.splice(index, 1);
    }
}

async function save() {
    isSaving.value = true;
    errors.value = {};

    const body: Record<string, unknown> = {
        name: name.value,
        description: description.value || null,
        ingredients: ingredients.value.map(i => ({
            name: i.name,
            quantity: i.quantity || null,
            unit: i.unit || null,
            notes: i.notes || null,
        })),
        instructions: instructions.value,
        prep_time: prepTime.value,
        cook_time: cookTime.value,
        servings: servings.value,
        image_url: imageUrl.value || null,
        source_url: sourceUrl.value || null,
        cuisine_id: cuisineId.value && cuisineId.value !== 'none' ? Number(cuisineId.value) : null,
        difficulty: difficulty.value,
        tag_ids: selectedTagIds.value,
        notes: notes.value || null,
    };

    try {
        const response = await fetch('/recipes', {
            method: 'POST',
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
            const recipe = await response.json();
            router.visit(`/recipes/${recipe.id}`);
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
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4 items-center">
            <form @submit.prevent="save" class="w-full max-w-2xl space-y-6">
                <h2 class="text-lg font-semibold">New Recipe</h2>
                <!-- Name -->
                <div class="space-y-2">
                    <Label for="recipe-name">Name</Label>
                    <Input id="recipe-name" v-model="name" placeholder="e.g. Chicken Parmesan" required />
                    <p v-if="errors.name" class="text-sm text-destructive">{{ errors.name[0] }}</p>
                </div>

                <!-- Description -->
                <div class="space-y-2">
                    <Label for="recipe-description">Description</Label>
                    <Textarea id="recipe-description" v-model="description" placeholder="Brief description..." rows="2" />
                    <p v-if="errors.description" class="text-sm text-destructive">{{ errors.description[0] }}</p>
                </div>

                <!-- Difficulty, Times, Servings -->
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <div class="space-y-2">
                        <Label for="recipe-difficulty">Difficulty</Label>
                        <Select v-model="difficulty">
                            <SelectTrigger id="recipe-difficulty">
                                <SelectValue placeholder="Select difficulty" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="(label, value) in DIFFICULTY_LABELS"
                                    :key="value"
                                    :value="value"
                                >
                                    {{ label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="errors.difficulty" class="text-sm text-destructive">{{ errors.difficulty[0] }}</p>
                    </div>
                    <div class="space-y-2">
                        <Label for="recipe-prep">Prep (min)</Label>
                        <Input id="recipe-prep" v-model.number="prepTime" type="number" min="0" required />
                        <p v-if="errors.prep_time" class="text-sm text-destructive">{{ errors.prep_time[0] }}</p>
                    </div>
                    <div class="space-y-2">
                        <Label for="recipe-cook">Cook (min)</Label>
                        <Input id="recipe-cook" v-model.number="cookTime" type="number" min="0" required />
                        <p v-if="errors.cook_time" class="text-sm text-destructive">{{ errors.cook_time[0] }}</p>
                    </div>
                    <div class="space-y-2">
                        <Label for="recipe-servings">Servings</Label>
                        <Input id="recipe-servings" v-model.number="servings" type="number" min="1" required />
                        <p v-if="errors.servings" class="text-sm text-destructive">{{ errors.servings[0] }}</p>
                    </div>
                </div>

                <!-- Cuisine -->
                <div class="space-y-2">
                    <Label>Cuisine</Label>
                    <div class="flex items-center gap-2">
                        <Select v-model="cuisineId">
                            <SelectTrigger class="flex-1">
                                <SelectValue placeholder="Select cuisine" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">None</SelectItem>
                                <SelectItem
                                    v-for="c in allCuisines"
                                    :key="c.id"
                                    :value="String(c.id)"
                                >
                                    {{ c.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <Popover v-model:open="showCuisineCreate">
                            <PopoverTrigger as-child>
                                <Button type="button" variant="outline" size="sm">
                                    <Plus class="h-3.5 w-3.5 mr-1" />
                                    New
                                </Button>
                            </PopoverTrigger>
                            <PopoverContent class="w-64 p-3">
                                <form @submit.prevent="createCuisine" class="flex flex-col gap-2">
                                    <Label class="text-sm">New Cuisine</Label>
                                    <Input v-model="newCuisineName" placeholder="e.g. Turkish" class="h-8 text-sm" />
                                    <Button type="submit" size="sm" :disabled="isCreatingCuisine || !newCuisineName.trim()">
                                        {{ isCreatingCuisine ? 'Adding...' : 'Add' }}
                                    </Button>
                                </form>
                            </PopoverContent>
                        </Popover>
                    </div>
                    <p v-if="errors.cuisine_id" class="text-sm text-destructive">{{ errors.cuisine_id[0] }}</p>
                </div>

                <!-- Ingredients -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <Label>Ingredients</Label>
                        <Button type="button" variant="outline" size="sm" @click="addIngredient">
                            <Plus class="h-3.5 w-3.5 mr-1" />
                            Add
                        </Button>
                    </div>
                    <div
                        v-for="(ingredient, index) in ingredients"
                        :key="index"
                        class="flex items-start gap-2"
                    >
                        <div class="flex-1 grid grid-cols-4 gap-1.5">
                            <Input
                                v-model="ingredient.name"
                                placeholder="Name"
                                class="col-span-2 h-8 text-sm"
                                required
                            />
                            <Input
                                v-model="ingredient.quantity"
                                placeholder="Qty"
                                class="h-8 text-sm"
                            />
                            <Input
                                v-model="ingredient.unit"
                                placeholder="Unit"
                                class="h-8 text-sm"
                            />
                        </div>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="h-8 w-8 shrink-0 text-destructive"
                            :disabled="ingredients.length <= 1"
                            @click="removeIngredient(index)"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                        </Button>
                    </div>
                    <p v-if="errors.ingredients" class="text-sm text-destructive">{{ errors.ingredients[0] }}</p>
                </div>

                <!-- Instructions -->
                <div class="space-y-2">
                    <Label for="recipe-instructions">Instructions</Label>
                    <Textarea id="recipe-instructions" v-model="instructions" placeholder="Step-by-step instructions..." rows="6" required />
                    <p v-if="errors.instructions" class="text-sm text-destructive">{{ errors.instructions[0] }}</p>
                </div>

                <!-- Tags -->
                <div class="space-y-2">
                    <Label>Tags</Label>
                    <!-- Selected tags chips -->
                    <div v-if="selectedTags.length > 0" class="flex flex-wrap gap-1.5">
                        <Badge
                            v-for="tag in selectedTags"
                            :key="tag.id"
                            variant="secondary"
                            class="gap-1 cursor-pointer"
                            @click="removeTag(tag.id)"
                        >
                            {{ tag.name }}
                            <X class="h-3 w-3" />
                        </Badge>
                    </div>
                    <!-- Tag selection -->
                    <Popover>
                        <PopoverTrigger as-child>
                            <Button type="button" variant="outline" size="sm">
                                <Plus class="h-3.5 w-3.5 mr-1" />
                                {{ selectedTagIds.length > 0 ? 'Edit tags' : 'Add tags' }}
                            </Button>
                        </PopoverTrigger>
                        <PopoverContent class="w-64 p-3">
                            <div class="space-y-3 max-h-48 overflow-y-auto">
                                <div
                                    v-for="tag in allTags"
                                    :key="tag.id"
                                    class="flex items-center gap-2"
                                >
                                    <Checkbox
                                        :id="`tag-${tag.id}`"
                                        :model-value="selectedTagIds.includes(tag.id)"
                                        @update:model-value="toggleTag(tag.id)"
                                    />
                                    <label :for="`tag-${tag.id}`" class="text-sm cursor-pointer">{{ tag.name }}</label>
                                </div>
                            </div>
                            <div class="border-t mt-3 pt-3">
                                <form v-if="showTagCreate" @submit.prevent="createTag" class="flex flex-col gap-2">
                                    <Input v-model="newTagName" placeholder="Tag name" class="h-8 text-sm" />
                                    <div class="flex gap-2">
                                        <Button type="submit" size="sm" class="flex-1" :disabled="isCreatingTag || !newTagName.trim()">
                                            {{ isCreatingTag ? 'Adding...' : 'Add' }}
                                        </Button>
                                        <Button type="button" variant="outline" size="sm" @click="showTagCreate = false">Cancel</Button>
                                    </div>
                                </form>
                                <Button v-else type="button" variant="ghost" size="sm" class="w-full" @click="showTagCreate = true">
                                    <Plus class="h-3.5 w-3.5 mr-1" />
                                    Add new tag
                                </Button>
                            </div>
                        </PopoverContent>
                    </Popover>
                    <p v-if="errors.tag_ids" class="text-sm text-destructive">{{ errors.tag_ids[0] }}</p>
                </div>

                <!-- Source URL & Image URL -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="recipe-source">Source URL</Label>
                        <Input id="recipe-source" v-model="sourceUrl" placeholder="https://..." />
                        <p v-if="errors.source_url" class="text-sm text-destructive">{{ errors.source_url[0] }}</p>
                    </div>
                    <div class="space-y-2">
                        <Label for="recipe-image">Image URL</Label>
                        <Input id="recipe-image" v-model="imageUrl" placeholder="https://..." />
                        <p v-if="errors.image_url" class="text-sm text-destructive">{{ errors.image_url[0] }}</p>
                    </div>
                </div>

                <!-- Notes -->
                <div class="space-y-2">
                    <Label for="recipe-notes">Notes</Label>
                    <Textarea id="recipe-notes" v-model="notes" placeholder="Personal notes..." rows="3" />
                    <p v-if="errors.notes" class="text-sm text-destructive">{{ errors.notes[0] }}</p>
                </div>

                <!-- Actions -->
                <div class="flex gap-3">
                    <Button type="button" variant="outline" @click="router.visit('/recipes')">
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="isSaving">
                        {{ isSaving ? 'Creating...' : 'Create Recipe' }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
