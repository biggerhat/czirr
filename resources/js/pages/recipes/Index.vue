<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { BookOpen, CookingPot, Pencil, Plus, Search, Trash2, X } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import RecipeCard from '@/components/recipes/RecipeCard.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { Recipe, RecipeDifficulty, RecipeTag } from '@/types/recipes';
import { DIFFICULTY_LABELS } from '@/types/recipes';

const props = defineProps<{
    recipes: Recipe[];
    tags: RecipeTag[];
    can: {
        create: boolean;
        edit: boolean;
        delete: boolean;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Recipes' },
];

// Search
const searchQuery = ref('');

// Tag filter
const selectedTagIds = ref<Set<number>>(new Set());

function toggleTag(tagId: number) {
    const next = new Set(selectedTagIds.value);
    if (next.has(tagId)) {
        next.delete(tagId);
    } else {
        next.add(tagId);
    }
    selectedTagIds.value = next;
}

function clearFilters() {
    searchQuery.value = '';
    selectedTagIds.value = new Set();
}

const hasActiveFilters = computed(() => searchQuery.value.trim() !== '' || selectedTagIds.value.size > 0);

// Sort & group
type SortOption = 'name' | 'updated' | 'difficulty' | 'total_time' | 'cuisine' | 'tags';
type GroupOption = 'none' | 'difficulty' | 'cuisine';

const sortBy = ref<SortOption>('updated');
const groupBy = ref<GroupOption>('none');

const SORT_LABELS: Record<SortOption, string> = {
    updated: 'Recently Updated',
    name: 'Name',
    difficulty: 'Difficulty',
    total_time: 'Total Time',
    cuisine: 'Cuisine',
    tags: 'Tags',
};

const GROUP_LABELS: Record<GroupOption, string> = {
    none: 'None',
    difficulty: 'Difficulty',
    cuisine: 'Cuisine',
};

const difficultyOrder: Record<RecipeDifficulty, number> = { easy: 0, medium: 1, hard: 2 };

// Filter → Sort → Group pipeline
const filteredRecipes = computed(() => {
    let recipes = props.recipes;

    const query = searchQuery.value.trim().toLowerCase();
    if (query) {
        recipes = recipes.filter(r =>
            r.name.toLowerCase().includes(query) ||
            (r.description?.toLowerCase().includes(query)) ||
            (r.tags?.some(t => t.name.toLowerCase().includes(query))),
        );
    }

    if (selectedTagIds.value.size > 0) {
        recipes = recipes.filter(r =>
            r.tags?.some(t => selectedTagIds.value.has(t.id)),
        );
    }

    return recipes;
});

const sortedRecipes = computed(() => {
    const recipes = [...filteredRecipes.value];

    recipes.sort((a, b) => {
        switch (sortBy.value) {
            case 'name':
                return a.name.localeCompare(b.name);
            case 'difficulty':
                return difficultyOrder[a.difficulty] - difficultyOrder[b.difficulty];
            case 'total_time':
                return (a.prep_time + a.cook_time) - (b.prep_time + b.cook_time);
            case 'cuisine':
                return (a.cuisine?.name ?? '').localeCompare(b.cuisine?.name ?? '');
            case 'tags':
                return (a.tags?.map(t => t.name).sort().join(', ') ?? '')
                    .localeCompare(b.tags?.map(t => t.name).sort().join(', ') ?? '');
            case 'updated':
            default:
                return new Date(b.updated_at).getTime() - new Date(a.updated_at).getTime();
        }
    });

    return recipes;
});

type RecipeGroup = { label: string; recipes: Recipe[] };

const groupedRecipes = computed((): RecipeGroup[] => {
    if (groupBy.value === 'none') {
        return [{ label: '', recipes: sortedRecipes.value }];
    }

    const map = new Map<string, Recipe[]>();

    for (const recipe of sortedRecipes.value) {
        let key: string;
        if (groupBy.value === 'difficulty') {
            key = DIFFICULTY_LABELS[recipe.difficulty];
        } else {
            key = recipe.cuisine?.name || 'Uncategorized';
        }
        if (!map.has(key)) map.set(key, []);
        map.get(key)!.push(recipe);
    }

    // For difficulty, sort groups by difficulty order
    if (groupBy.value === 'difficulty') {
        const order = ['Easy', 'Medium', 'Hard'];
        return order
            .filter(label => map.has(label))
            .map(label => ({ label, recipes: map.get(label)! }));
    }

    // For cuisine, sort groups alphabetically
    return [...map.entries()]
        .sort(([a], [b]) => {
            if (a === 'Uncategorized') return 1;
            if (b === 'Uncategorized') return -1;
            return a.localeCompare(b);
        })
        .map(([label, recipes]) => ({ label, recipes }));
});

// Delete
const showDeleteDialog = ref(false);
const deletingRecipe = ref<Recipe | null>(null);
const isDeleting = ref(false);

function openDelete(recipe: Recipe) {
    deletingRecipe.value = recipe;
    showDeleteDialog.value = true;
}

async function confirmDelete() {
    if (!deletingRecipe.value) return;
    isDeleting.value = true;

    await fetch(`/recipes/${deletingRecipe.value.id}`, {
        method: 'DELETE',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': decodeURIComponent(
                document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
            ),
        },
    });
    isDeleting.value = false;
    showDeleteDialog.value = false;
    router.reload();
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold">Recipes</h2>
                <div class="flex items-center gap-2">
                    <Link href="/cookbooks">
                        <Button variant="outline" size="sm">
                            <BookOpen class="h-4 w-4 mr-1" />
                            Cookbooks
                        </Button>
                    </Link>
                    <Link v-if="can.create" href="/recipes/create">
                        <Button size="sm">
                            <Plus class="h-4 w-4 mr-1" />
                            New Recipe
                        </Button>
                    </Link>
                </div>
            </div>

            <!-- Search & filters -->
            <div v-if="recipes.length > 0" class="space-y-3">
                <!-- Search bar -->
                <div class="relative">
                    <Search class="absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        v-model="searchQuery"
                        placeholder="Search recipes by name, description, or tag..."
                        class="pl-9 pr-9"
                    />
                    <button
                        v-if="searchQuery"
                        class="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                        @click="searchQuery = ''"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <!-- Tag filter -->
                <div v-if="tags.length > 0" class="flex flex-wrap items-center gap-1.5">
                    <span class="text-sm text-muted-foreground mr-1">Tags</span>
                    <Badge
                        v-for="tag in tags"
                        :key="tag.id"
                        :variant="selectedTagIds.has(tag.id) ? 'default' : 'outline'"
                        class="cursor-pointer text-xs"
                        @click="toggleTag(tag.id)"
                    >
                        {{ tag.name }}
                    </Badge>
                    <button
                        v-if="hasActiveFilters"
                        class="text-xs text-muted-foreground hover:text-foreground ml-1"
                        @click="clearFilters"
                    >
                        Clear filters
                    </button>
                </div>

                <!-- Sort, Group & count -->
                <div class="flex flex-col sm:flex-row flex-wrap items-start sm:items-center gap-3">
                    <div class="flex items-center gap-2 w-full sm:w-auto">
                        <span class="text-sm text-muted-foreground shrink-0">Sort</span>
                        <Select v-model="sortBy">
                            <SelectTrigger class="h-8 w-full sm:w-[160px] text-sm">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="(label, value) in SORT_LABELS"
                                    :key="value"
                                    :value="value"
                                >
                                    {{ label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="flex items-center gap-2 w-full sm:w-auto">
                        <span class="text-sm text-muted-foreground shrink-0">Group</span>
                        <Select v-model="groupBy">
                            <SelectTrigger class="h-8 w-full sm:w-[140px] text-sm">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="(label, value) in GROUP_LABELS"
                                    :key="value"
                                    :value="value"
                                >
                                    {{ label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <span class="text-sm text-muted-foreground ml-auto">
                        {{ filteredRecipes.length }} {{ filteredRecipes.length === 1 ? 'recipe' : 'recipes' }}
                        <span v-if="filteredRecipes.length !== recipes.length"> of {{ recipes.length }}</span>
                    </span>
                </div>
            </div>

            <!-- Empty state -->
            <div v-if="recipes.length === 0" class="rounded-lg border border-dashed p-8 text-center">
                <CookingPot class="mx-auto mb-2 h-8 w-8 text-muted-foreground/30" />
                <p class="text-sm text-muted-foreground">No recipes yet.</p>
                <p v-if="can.create" class="mt-1 text-xs text-muted-foreground/70">Click "New Recipe" to get started.</p>
            </div>

            <!-- No results after filtering -->
            <div v-else-if="filteredRecipes.length === 0" class="rounded-lg border border-dashed p-8 text-center">
                <Search class="mx-auto mb-2 h-8 w-8 text-muted-foreground/30" />
                <p class="text-sm text-muted-foreground">No recipes match your filters.</p>
                <p class="mt-1 text-xs text-muted-foreground/70">
                    Try a different search or
                    <button class="text-primary hover:underline" @click="clearFilters">clear filters</button>.
                </p>
            </div>

            <!-- Grouped recipe grid -->
            <template v-else>
                <div v-for="group in groupedRecipes" :key="group.label" class="space-y-3">
                    <h3 v-if="group.label" class="text-sm font-medium text-muted-foreground">
                        {{ group.label }}
                    </h3>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <Link
                            v-for="recipe in group.recipes"
                            :key="recipe.id"
                            :href="`/recipes/${recipe.id}`"
                            class="rounded-lg border p-4 hover:border-primary/50 transition-colors"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex-1 min-w-0">
                                    <RecipeCard :recipe="recipe" />
                                </div>
                                <div v-if="can.edit || can.delete" class="flex items-center gap-1 shrink-0">
                                    <Link v-if="can.edit" :href="`/recipes/${recipe.id}/edit`" @click.stop>
                                        <Button variant="outline" size="icon" class="h-8 w-8">
                                            <Pencil class="h-3.5 w-3.5" />
                                        </Button>
                                    </Link>
                                    <Button v-if="can.delete" variant="outline" size="icon" class="h-8 w-8 text-destructive" @click.prevent="openDelete(recipe)">
                                        <Trash2 class="h-3.5 w-3.5" />
                                    </Button>
                                </div>
                            </div>
                        </Link>
                    </div>
                </div>
            </template>

            <!-- Delete dialog -->
            <Dialog :open="showDeleteDialog" @update:open="showDeleteDialog = $event">
                <DialogContent class="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Delete recipe?</DialogTitle>
                        <DialogDescription>
                            This will permanently delete "{{ deletingRecipe?.name }}". This cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter class="gap-2">
                        <Button variant="outline" @click="showDeleteDialog = false">Cancel</Button>
                        <Button variant="destructive" :disabled="isDeleting" @click="confirmDelete">
                            {{ isDeleting ? 'Deleting...' : 'Delete' }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>
