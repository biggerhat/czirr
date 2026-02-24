<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import CookbookModal from '@/components/recipes/CookbookModal.vue';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { FamilyMember } from '@/types/calendar';
import type { Cookbook, Recipe } from '@/types/recipes';
import { COOKBOOK_VISIBILITY_LABELS } from '@/types/recipes';

const props = defineProps<{
    cookbook: Cookbook;
    allRecipes: Recipe[];
    familyMembers: FamilyMember[];
    can: {
        edit: boolean;
        delete: boolean;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Recipes', href: '/recipes' },
    { title: 'Cookbooks', href: '/cookbooks' },
    { title: props.cookbook.name },
];

const recipes = computed(() => props.cookbook.recipes ?? []);

// Cookbook edit modal
const showCookbookModal = ref(false);

function onCookbookSaved() {
    router.reload();
}

// Add recipe picker
const showAddDialog = ref(false);
const selectedRecipeId = ref<string>('');
const isAddingRecipe = ref(false);

const availableRecipes = computed(() => {
    const existingIds = new Set(recipes.value.map(r => r.id));
    return props.allRecipes.filter(r => !existingIds.has(r.id));
});

async function addRecipe() {
    if (!selectedRecipeId.value) return;
    isAddingRecipe.value = true;

    await fetch(`/cookbooks/${props.cookbook.id}/recipes`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': decodeURIComponent(
                document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
            ),
        },
        body: JSON.stringify({ recipe_id: Number(selectedRecipeId.value) }),
    });

    isAddingRecipe.value = false;
    showAddDialog.value = false;
    selectedRecipeId.value = '';
    router.reload();
}

// Remove recipe
async function removeRecipe(recipe: Recipe) {
    await fetch(`/cookbooks/${props.cookbook.id}/recipes/${recipe.id}`, {
        method: 'DELETE',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': decodeURIComponent(
                document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
            ),
        },
    });
    router.reload();
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <!-- Header -->
            <div class="flex items-start justify-between gap-4">
                <div class="space-y-1">
                    <div class="flex items-center gap-3">
                        <h2 class="text-lg font-semibold">{{ cookbook.name }}</h2>
                        <Badge variant="outline">{{ COOKBOOK_VISIBILITY_LABELS[cookbook.visibility] }}</Badge>
                    </div>
                    <p v-if="cookbook.description" class="text-sm text-muted-foreground">{{ cookbook.description }}</p>
                </div>
                <div v-if="can.edit" class="flex items-center gap-2">
                    <Button variant="outline" size="sm" @click="showAddDialog = true">
                        <Plus class="h-4 w-4 mr-1" />
                        Add Recipe
                    </Button>
                    <Button variant="outline" size="sm" @click="showCookbookModal = true">
                        <Pencil class="h-3.5 w-3.5 mr-1" />
                        Edit
                    </Button>
                </div>
            </div>

            <!-- Empty state -->
            <div v-if="recipes.length === 0" class="rounded-lg border border-dashed p-8 text-center text-muted-foreground">
                No recipes in this cookbook yet. {{ can.edit ? 'Click "Add Recipe" to get started.' : '' }}
            </div>

            <!-- Recipe grid -->
            <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="recipe in recipes"
                    :key="recipe.id"
                    :href="`/recipes/${recipe.id}`"
                    class="rounded-lg border p-4 hover:border-primary/50 transition-colors"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex-1 min-w-0">
                            <RecipeCard :recipe="recipe" />
                        </div>
                        <Button
                            v-if="can.edit"
                            variant="outline"
                            size="icon"
                            class="h-8 w-8 shrink-0 text-destructive"
                            @click.prevent="removeRecipe(recipe)"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                        </Button>
                    </div>
                </Link>
            </div>

            <!-- Add recipe dialog -->
            <Dialog :open="showAddDialog" @update:open="showAddDialog = $event">
                <DialogContent class="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Add recipe to cookbook</DialogTitle>
                        <DialogDescription>
                            Select a recipe to add to "{{ cookbook.name }}".
                        </DialogDescription>
                    </DialogHeader>
                    <div v-if="availableRecipes.length === 0" class="py-4 text-sm text-muted-foreground text-center">
                        All recipes are already in this cookbook.
                    </div>
                    <div v-else class="py-4">
                        <Select v-model="selectedRecipeId">
                            <SelectTrigger>
                                <SelectValue placeholder="Select a recipe" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="recipe in availableRecipes"
                                    :key="recipe.id"
                                    :value="String(recipe.id)"
                                >
                                    {{ recipe.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <DialogFooter class="gap-2">
                        <Button variant="outline" @click="showAddDialog = false">Cancel</Button>
                        <Button
                            :disabled="!selectedRecipeId || isAddingRecipe"
                            @click="addRecipe"
                        >
                            {{ isAddingRecipe ? 'Adding...' : 'Add' }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Cookbook edit modal -->
            <CookbookModal
                :cookbook="cookbook"
                :family-members="familyMembers"
                :open="showCookbookModal"
                @update:open="showCookbookModal = $event"
                @saved="onCookbookSaved"
            />
        </div>
    </AppLayout>
</template>
