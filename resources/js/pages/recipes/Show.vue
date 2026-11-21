<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    BookOpen,
    Clock,
    ExternalLink,
    Pencil,
    Star,
    Timer,
    Users,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardHeader,
    CardContent,
    CardTitle,
    CardDescription,
    CardAction,
} from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/AppLayout.vue';
import { api } from '@/lib/api';
import type { BreadcrumbItem } from '@/types';
import type { Recipe, Cookbook } from '@/types/recipes';
import { DIFFICULTY_LABELS, DIFFICULTY_COLORS } from '@/types/recipes';

type Rating = { mine: number | null; average: number | null; count: number };

const props = defineProps<{
    recipe: Recipe;
    cookbooks: Cookbook[];
    rating: Rating;
    can: {
        edit: boolean;
        delete: boolean;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Recipes', href: '/recipes' },
    { title: props.recipe.name },
];

const rating = ref<Rating>({ ...props.rating });

async function rate(value: number) {
    // Tapping your current rating clears it.
    const clearing = rating.value.mine === value;
    const res = clearing
        ? await api<Rating>('DELETE', `/recipes/${props.recipe.id}/rating`)
        : await api<Rating>('PUT', `/recipes/${props.recipe.id}/rating`, {
              rating: value,
          });
    if (res.ok && res.data) rating.value = res.data;
}

const instructionSteps = computed(() =>
    props.recipe.instructions
        .split(/\n\n+/)
        .map((s) => s.trim())
        .filter(Boolean),
);

const hasFooterContent = computed(
    () =>
        props.recipe.notes ||
        props.recipe.source_url ||
        (props.recipe.cookbooks && props.recipe.cookbooks.length > 0),
);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="mx-auto flex h-full w-full max-w-4xl flex-1 flex-col gap-6 p-4"
        >
            <!-- Hero header card -->
            <Card class="overflow-hidden">
                <img
                    v-if="recipe.image_url && /^https?:\/\//i.test(recipe.image_url)"
                    :src="recipe.image_url"
                    :alt="recipe.name"
                    referrerpolicy="no-referrer"
                    class="max-h-72 w-full object-cover"
                    @error="($event.target as HTMLImageElement).style.display = 'none'"
                />
                <CardHeader>
                    <h2 class="text-2xl leading-none font-semibold">{{ recipe.name }}</h2>
                    <CardDescription v-if="recipe.description">{{
                        recipe.description
                    }}</CardDescription>
                    <div class="flex items-center gap-2">
                        <div
                            class="flex"
                            role="radiogroup"
                            aria-label="Your rating"
                        >
                            <button
                                v-for="n in 5"
                                :key="n"
                                type="button"
                                class="p-0.5"
                                :aria-label="`${n} star${n === 1 ? '' : 's'}`"
                                @click="rate(n)"
                            >
                                <Star
                                    class="h-5 w-5"
                                    :class="
                                        n <= (rating.mine ?? 0)
                                            ? 'fill-yellow-400 text-yellow-400'
                                            : 'text-muted-foreground/40'
                                    "
                                />
                            </button>
                        </div>
                        <span
                            v-if="rating.count"
                            class="text-xs text-muted-foreground"
                        >
                            family avg {{ rating.average }} ({{ rating.count }})
                        </span>
                        <span v-else class="text-xs text-muted-foreground"
                            >Rate this recipe</span
                        >
                    </div>
                    <CardAction v-if="can.edit">
                        <Link :href="`/recipes/${recipe.id}/edit`">
                            <Button variant="outline" size="sm">
                                <Pencil class="mr-1 h-3.5 w-3.5" />
                                Edit
                            </Button>
                        </Link>
                    </CardAction>
                </CardHeader>

                <CardContent class="space-y-4">
                    <!-- Stat tiles -->
                    <div
                        class="grid grid-cols-2 gap-px overflow-hidden rounded-lg border bg-border sm:grid-cols-4"
                    >
                        <div class="flex items-center gap-2 bg-card p-2.5">
                            <Badge
                                :class="DIFFICULTY_COLORS[recipe.difficulty]"
                                variant="secondary"
                            >
                                {{ DIFFICULTY_LABELS[recipe.difficulty] }}
                            </Badge>
                        </div>
                        <div class="flex items-center gap-2 bg-card p-2.5">
                            <Timer
                                class="h-3.5 w-3.5 shrink-0 text-muted-foreground"
                            />
                            <div class="text-sm">
                                <span class="font-medium"
                                    >{{ recipe.prep_time }}m</span
                                >
                                <span class="text-muted-foreground"> prep</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 bg-card p-2.5">
                            <Clock
                                class="h-3.5 w-3.5 shrink-0 text-muted-foreground"
                            />
                            <div class="text-sm">
                                <span class="font-medium"
                                    >{{ recipe.cook_time }}m</span
                                >
                                <span class="text-muted-foreground"> cook</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 bg-card p-2.5">
                            <Users
                                class="h-3.5 w-3.5 shrink-0 text-muted-foreground"
                            />
                            <div class="text-sm">
                                <span class="font-medium">{{
                                    recipe.servings
                                }}</span>
                                <span class="text-muted-foreground">
                                    {{
                                        recipe.servings === 1
                                            ? 'serving'
                                            : 'servings'
                                    }}</span
                                >
                            </div>
                        </div>
                    </div>

                    <!-- Cuisine + Tags -->
                    <div
                        v-if="
                            recipe.cuisine ||
                            (recipe.tags && recipe.tags.length > 0)
                        "
                        class="flex flex-wrap gap-1.5"
                    >
                        <Badge v-if="recipe.cuisine" variant="outline">{{
                            recipe.cuisine.name
                        }}</Badge>
                        <Badge
                            v-for="tag in recipe.tags"
                            :key="tag.id"
                            variant="outline"
                            class="text-xs"
                        >
                            {{ tag.name }}
                        </Badge>
                    </div>
                </CardContent>
            </Card>

            <!-- Two-column layout: Ingredients + Instructions -->
            <div class="grid gap-6 md:grid-cols-[1fr_2fr]">
                <!-- Ingredients card -->
                <Card>
                    <CardHeader>
                        <CardTitle class="text-lg">Ingredients</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ul class="space-y-2">
                            <li
                                v-for="(
                                    ingredient, index
                                ) in recipe.ingredients"
                                :key="index"
                                class="flex items-baseline justify-between gap-3 text-sm"
                            >
                                <span class="font-medium">
                                    {{ ingredient.name }}
                                    <span
                                        v-if="ingredient.notes"
                                        class="font-normal text-muted-foreground"
                                    >
                                        ({{ ingredient.notes }})</span
                                    >
                                </span>
                                <span
                                    v-if="
                                        ingredient.quantity || ingredient.unit
                                    "
                                    class="shrink-0 text-right text-muted-foreground"
                                >
                                    <span v-if="ingredient.quantity">{{
                                        ingredient.quantity
                                    }}</span>
                                    <span v-if="ingredient.unit">{{
                                        ' ' + ingredient.unit
                                    }}</span>
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
                                <span
                                    class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary text-xs text-primary-foreground"
                                >
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
                        <h3 class="mb-1.5 text-sm font-semibold">Notes</h3>
                        <p
                            class="text-sm whitespace-pre-wrap text-muted-foreground"
                        >
                            {{ recipe.notes }}
                        </p>
                    </div>

                    <Separator
                        v-if="
                            recipe.notes &&
                            (recipe.source_url ||
                                (recipe.cookbooks &&
                                    recipe.cookbooks.length > 0))
                        "
                    />

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

                    <Separator
                        v-if="
                            recipe.source_url &&
                            recipe.cookbooks &&
                            recipe.cookbooks.length > 0
                        "
                    />

                    <!-- Cookbooks -->
                    <div v-if="recipe.cookbooks && recipe.cookbooks.length > 0">
                        <h3
                            class="mb-1.5 flex items-center gap-1.5 text-sm font-semibold"
                        >
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
                                <Badge
                                    variant="secondary"
                                    class="cursor-pointer hover:bg-secondary/80"
                                >
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
