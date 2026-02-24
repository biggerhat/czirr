<script setup lang="ts">
import { ChevronLeft, ChevronRight, Plus } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import type { CalendarView } from '@/types/calendar';

defineProps<{
    title: string;
    view: CalendarView;
}>();

defineEmits<{
    prev: [];
    next: [];
    today: [];
    'update:view': [view: CalendarView];
    'new-event': [];
}>();

const views: { label: string; value: CalendarView }[] = [
    { label: 'Month', value: 'month' },
    { label: 'Week', value: 'week' },
    { label: 'Day', value: 'day' },
    { label: 'Agenda', value: 'agenda' },
];
</script>

<template>
    <div class="space-y-2 pb-4">
        <!-- Top row: nav + title + desktop actions -->
        <div class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <Button variant="outline" size="icon" @click="$emit('prev')">
                    <ChevronLeft class="h-4 w-4" />
                </Button>
                <Button variant="outline" size="icon" @click="$emit('next')">
                    <ChevronRight class="h-4 w-4" />
                </Button>
                <Button variant="outline" size="sm" @click="$emit('today')">
                    Today
                </Button>
                <h2 class="text-lg font-semibold ml-2 hidden sm:block">{{ title }}</h2>
            </div>

            <div class="flex items-center gap-2">
                <div class="hidden sm:flex rounded-lg border border-border overflow-hidden">
                    <button
                        v-for="v in views"
                        :key="v.value"
                        :class="[
                            'px-3 py-1.5 text-sm font-medium transition-colors',
                            view === v.value
                                ? 'bg-primary text-primary-foreground'
                                : 'hover:bg-muted text-muted-foreground',
                        ]"
                        @click="$emit('update:view', v.value)"
                    >
                        {{ v.label }}
                    </button>
                </div>
                <Button size="sm" @click="$emit('new-event')">
                    <Plus class="h-4 w-4 sm:mr-1" />
                    <span class="hidden sm:inline">New Event</span>
                </Button>
            </div>
        </div>

        <!-- Mobile second row: title + view toggle -->
        <div class="flex items-center justify-between gap-2 sm:hidden">
            <h2 class="text-sm font-semibold">{{ title }}</h2>
            <div class="flex rounded-lg border border-border overflow-hidden">
                <button
                    v-for="v in views"
                    :key="v.value"
                    :class="[
                        'px-2 py-1 text-xs font-medium transition-colors',
                        view === v.value
                            ? 'bg-primary text-primary-foreground'
                            : 'hover:bg-muted text-muted-foreground',
                    ]"
                    @click="$emit('update:view', v.value)"
                >
                    {{ v.label }}
                </button>
            </div>
        </div>
    </div>
</template>
