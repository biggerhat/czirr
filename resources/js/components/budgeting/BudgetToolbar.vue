<script setup lang="ts">
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';

defineProps<{
    title: string;
}>();

defineEmits<{
    prev: [];
    next: [];
    today: [];
}>();
</script>

<template>
    <div class="flex items-center gap-2 pb-4">
        <Button variant="outline" size="icon" @click="$emit('prev')">
            <ChevronLeft class="h-4 w-4" />
        </Button>
        <Button variant="outline" size="icon" @click="$emit('next')">
            <ChevronRight class="h-4 w-4" />
        </Button>
        <Button variant="outline" size="sm" @click="$emit('today')">
            Today
        </Button>
        <h2 class="text-lg font-semibold ml-2">{{ title }}</h2>
    </div>
</template>
