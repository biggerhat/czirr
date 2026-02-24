<script setup lang="ts">
import { computed } from 'vue';
import { toLocalDateString } from '@/lib/calendar';
import type { CalendarEvent } from '@/types/calendar';
import CalendarMonthCell from './CalendarMonthCell.vue';

const props = defineProps<{
    currentDate: Date;
    eventsByDate: Map<string, CalendarEvent[]>;
    timezone: string;
}>();

defineEmits<{
    'select-event': [event: CalendarEvent];
    'select-date': [date: string];
}>();

const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

const weeks = computed(() => {
    const year = props.currentDate.getFullYear();
    const month = props.currentDate.getMonth();
    const firstOfMonth = new Date(year, month, 1);
    const start = new Date(firstOfMonth);
    start.setDate(start.getDate() - start.getDay());

    const result: Date[][] = [];
    const day = new Date(start);

    for (let w = 0; w < 6; w++) {
        const week: Date[] = [];
        for (let d = 0; d < 7; d++) {
            week.push(new Date(day));
            day.setDate(day.getDate() + 1);
        }
        result.push(week);
    }
    return result;
});

function getEventsForDate(date: Date): CalendarEvent[] {
    const key = toLocalDateString(date, props.timezone);
    return props.eventsByDate.get(key) ?? [];
}

function isCurrentMonth(date: Date): boolean {
    return date.getMonth() === props.currentDate.getMonth();
}
</script>

<template>
    <div class="border-l border-t border-border rounded-lg overflow-hidden">
        <div class="grid grid-cols-7">
            <div
                v-for="name in dayNames"
                :key="name"
                class="border-b border-r border-border px-2 py-2 text-center text-xs font-medium text-muted-foreground bg-muted/50"
            >
                {{ name }}
            </div>
        </div>
        <div v-for="(week, wi) in weeks" :key="wi" class="grid grid-cols-7">
            <CalendarMonthCell
                v-for="(date, di) in week"
                :key="di"
                :date="date"
                :events="getEventsForDate(date)"
                :timezone="timezone"
                :is-current-month="isCurrentMonth(date)"
                @select-event="$emit('select-event', $event)"
                @select-date="$emit('select-date', $event)"
            />
        </div>
    </div>
</template>
