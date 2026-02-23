<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Clock, Users } from 'lucide-vue-next';
import type { Recipe } from '@/types/recipes';
import { DIFFICULTY_LABELS, DIFFICULTY_COLORS } from '@/types/recipes';

defineProps<{
    recipe: Recipe;
}>();
</script>

<template>
    <div class="space-y-3">
        <div class="flex items-start justify-between gap-2">
            <span class="font-medium truncate">{{ recipe.name }}</span>
        </div>

        <div class="flex flex-wrap items-center gap-1.5">
            <Badge :class="DIFFICULTY_COLORS[recipe.difficulty]" variant="secondary">
                {{ DIFFICULTY_LABELS[recipe.difficulty] }}
            </Badge>
            <Badge v-if="recipe.cuisine" variant="outline">{{ recipe.cuisine.name }}</Badge>
        </div>

        <div class="flex items-center gap-3 text-sm text-muted-foreground">
            <span class="flex items-center gap-1">
                <Clock class="h-3.5 w-3.5" />
                {{ recipe.prep_time + recipe.cook_time }}m
            </span>
            <span class="flex items-center gap-1">
                <Users class="h-3.5 w-3.5" />
                {{ recipe.servings }}
            </span>
        </div>

        <div v-if="recipe.tags && recipe.tags.length > 0" class="flex flex-wrap gap-1">
            <Badge v-for="tag in recipe.tags" :key="tag.id" variant="outline" class="text-xs">
                {{ tag.name }}
            </Badge>
        </div>
    </div>
</template>
