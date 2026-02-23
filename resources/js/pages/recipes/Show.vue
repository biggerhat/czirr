<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardHeader, CardContent, CardTitle, CardDescription, CardAction } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { BookOpen, Clock, ExternalLink, Pencil, Timer, Users } from 'lucide-vue-next';
import { computed } from 'vue';
import type { BreadcrumbItem } from '@/types';
import type { Recipe, Cookbook } from '@/types/recipes';
import { DIFFICULTY_LABELS, DIFFICULTY_COLORS } from '@/types/recipes';

const props = defineProps<{
    recipe: Recipe;
    cookbooks: Cookbook[];
    can: {
        edit: boolean;
        delete: boolean;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Recipes', href: '/recipes' },
    { title: props.recipe.name },
];

const instructionSteps = computed(() =>
    props.recipe.instructions.split(/\n\n+/).map(s => s.trim()).filter(Boolean),
);

const hasFooterContent = computed(() =>
    props.recipe.notes || props.recipe.source_url || (props.recipe.cookbooks && props.recipe.cookbooks.length > 0),
);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4 max-w-4xl mx-auto w-full">
            <!-- Hero header card -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-2xl">{{ recipe.name }}</CardTitle>
                    <CardDescription v-if="recipe.description">{{ recipe.description }}</CardDescription>
                    <CardAction v-if="can.edit">
                        <Link :href="`/recipes/${recipe.id}/edit`">
                            <Button variant="outline" size="sm">
                                <Pencil class="h-3.5 w-3.5 mr-1" />
                                Edit
                            </Button>
                        </Link>
                    </CardAction>
                </CardHeader>

                <CardContent class="space-y-4">
                    <!-- Stat tiles -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <div class="bg-muted rounded-lg p-3 flex items-center gap-2.5">
                            <Badge :class="DIFFICULTY_COLORS[recipe.difficulty]" variant="secondary">
                                {{ DIFFICULTY_LABELS[recipe.difficulty] }}
                            </Badge>
                        </div>
                        <div class="bg-muted rounded-lg p-3 flex items-center gap-2.5">
                            <Timer class="h-4 w-4 text-muted-foreground shrink-0" />
                            <div class="text-sm">
                                <span class="font-medium">{{ recipe.prep_time }}m</span>
                                <span class="text-muted-foreground"> prep</span>
                            </div>
                        </div>
                        <div class="bg-muted rounded-lg p-3 flex items-center gap-2.5">
                            <Clock class="h-4 w-4 text-muted-foreground shrink-0" />
                            <div class="text-sm">
                                <span class="font-medium">{{ recipe.cook_time }}m</span>
                                <span class="text-muted-foreground"> cook</span>
                            </div>
                        </div>
                        <div class="bg-muted rounded-lg p-3 flex items-center gap-2.5">
                            <Users class="h-4 w-4 text-muted-foreground shrink-0" />
                            <div class="text-sm">
                                <span class="font-medium">{{ recipe.servings }}</span>
                                <span class="text-muted-foreground"> {{ recipe.servings === 1 ? 'serving' : 'servings' }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Cuisine + Tags -->
                    <div v-if="recipe.cuisine || (recipe.tags && recipe.tags.length > 0)" class="flex flex-wrap gap-1.5">
                        <Badge v-if="recipe.cuisine" variant="outline">{{ recipe.cuisine.name }}</Badge>
                        <Badge v-for="tag in recipe.tags" :key="tag.id" variant="outline" class="text-xs">
                            {{ tag.name }}
                        </Badge>
                    </div>
                </CardContent>
            </Card>

            <!-- Two-column layout: Ingredients + Instructions -->
            <div class="grid md:grid-cols-[1fr_2fr] gap-6">
                <!-- Ingredients card -->
                <Card>
                    <CardHeader>
                        <CardTitle class="text-lg">Ingredients</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ul class="space-y-2">
                            <li
                                v-for="(ingredient, index) in recipe.ingredients"
                                :key="index"
                                class="flex items-baseline justify-between gap-3 text-sm"
                            >
                                <span class="font-medium">
                                    {{ ingredient.name }}
                                    <span v-if="ingredient.notes" class="font-normal text-muted-foreground"> ({{ ingredient.notes }})</span>
                                </span>
                                <span v-if="ingredient.quantity || ingredient.unit" class="text-muted-foreground shrink-0 text-right">
                                    <span v-if="ingredient.quantity">{{ ingredient.quantity }}</span>
                                    <span v-if="ingredient.unit">{{ ' ' + ingredient.unit }}</span>
                                </span>
                            </li>
                        </ul>
                    </CardContent>
                </Card>

                <!-- Instructions card -->
                <Card>
                    <CardHeader>
                        <CardTitle class="text-lg">Instructions</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ol class="space-y-4">
                            <li
                                v-for="(step, index) in instructionSteps"
                                :key="index"
                                class="flex gap-3 text-sm"
                            >
                                <span class="bg-primary text-primary-foreground rounded-full w-6 h-6 text-xs flex items-center justify-center shrink-0 mt-0.5">
                                    {{ index + 1 }}
                                </span>
                                <span>{{ step }}</span>
                            </li>
                        </ol>
                    </CardContent>
                </Card>
            </div>

            <!-- Notes, Source, Cookbooks footer card -->
            <Card v-if="hasFooterContent">
                <CardContent class="space-y-4">
                    <!-- Notes -->
                    <div v-if="recipe.notes">
                        <h3 class="text-sm font-semibold mb-1.5">Notes</h3>
                        <p class="text-sm text-muted-foreground whitespace-pre-wrap">{{ recipe.notes }}</p>
                    </div>

                    <Separator v-if="recipe.notes && (recipe.source_url || (recipe.cookbooks && recipe.cookbooks.length > 0))" />

                    <!-- Source -->
                    <div v-if="recipe.source_url">
                        <a
                            :href="recipe.source_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-1.5 text-sm text-primary hover:underline"
                        >
                            <ExternalLink class="h-3.5 w-3.5" />
                            View source
                        </a>
                    </div>

                    <Separator v-if="recipe.source_url && recipe.cookbooks && recipe.cookbooks.length > 0" />

                    <!-- Cookbooks -->
                    <div v-if="recipe.cookbooks && recipe.cookbooks.length > 0">
                        <h3 class="text-sm font-semibold mb-1.5 flex items-center gap-1.5">
                            <BookOpen class="h-3.5 w-3.5" />
                            In Cookbooks
                        </h3>
                        <div class="flex flex-wrap gap-2">
                            <Link
                                v-for="cookbook in recipe.cookbooks"
                                :key="cookbook.id"
                                :href="`/cookbooks/${cookbook.id}`"
                                class="inline-flex"
                            >
                                <Badge variant="secondary" class="hover:bg-secondary/80 cursor-pointer">
                                    {{ cookbook.name }}
                                </Badge>
                            </Link>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
