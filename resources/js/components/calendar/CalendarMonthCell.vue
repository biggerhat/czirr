<script setup lang="ts">
import { computed } from 'vue';
import { toLocalDateString } from '@/lib/calendar';
import type { CalendarEvent } from '@/types/calendar';
import CalendarEventChip from './CalendarEventChip.vue';

const MAX_VISIBLE = 3;

const props = defineProps<{
    date: Date;
    events: CalendarEvent[];
    timezone: string;
    isCurrentMonth: boolean;
}>();

defineEmits<{
    'select-event': [event: CalendarEvent];
    'select-date': [date: string];
}>();

const dateStr = computed(() => toLocalDateString(props.date, props.timezone));
const dayNumber = computed(() => props.date.getDate());
const isToday = computed(() => dateStr.value === toLocalDateString(new Date(), props.timezone));
const visibleEvents = computed(() => props.events.slice(0, MAX_VISIBLE));
const overflowCount = computed(() => Math.max(0, props.events.length - MAX_VISIBLE));
</script>

<template>
    <div
        :class="[
            'min-h-[140px] border-b border-r border-border p-1 cursor-pointer transition-colors hover:bg-muted/50',
            !isCurrentMonth && 'bg-muted/30 text-muted-foreground',
        ]"
        @click="$emit('select-date', dateStr)"
    >
        <div class="flex justify-end mb-0.5">
            <span
                :class="[
                    'inline-flex h-6 w-6 items-center justify-center rounded-full text-xs font-medium',
                    isToday && 'bg-primary text-primary-foreground',
                ]"
            >
                {{ dayNumber }}
            </span>
        </div>
        <div class="space-y-0.5">
            <CalendarEventChip
                v-for="event in visibleEvents"
                :key="event.id"
                :event="event"
                :timezone="timezone"
                compact
                @click="$emit('select-event', event)"
            />
            <button
                v-if="overflowCount > 0"
                class="w-full text-left text-xs text-muted-foreground hover:text-foreground px-1"
                @click.stop="$emit('select-date', dateStr)"
            >
                +{{ overflowCount }} more
            </button>
        </div>
    </div>
</template>
