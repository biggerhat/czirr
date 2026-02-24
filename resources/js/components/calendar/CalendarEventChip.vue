<script setup lang="ts">
import { Repeat } from 'lucide-vue-next';
import { computed } from 'vue';
import { EVENT_COLORS, getEventColor, formatEventTime } from '@/lib/calendar';
import type { CalendarEvent } from '@/types/calendar';

const props = defineProps<{
    event: CalendarEvent;
    timezone: string;
    compact?: boolean;
}>();

defineEmits<{
    click: [event: CalendarEvent];
}>();

const colors = computed(() => EVENT_COLORS[getEventColor(props.event)] ?? EVENT_COLORS.blue);
const isRecurring = computed(() => !!props.event.rrule || !!props.event.is_occurrence);
</script>

<template>
    <button
        :class="[
            'w-full text-left rounded-md font-medium truncate cursor-pointer border transition-opacity hover:opacity-80',
            compact ? 'px-1 py-px text-[10px] leading-tight sm:px-1.5 sm:py-0.5 sm:text-xs sm:leading-normal' : 'px-1.5 py-0.5 text-xs',
            colors.bg,
            colors.text,
            colors.border,
        ]"
        @click.stop="$emit('click', event)"
    >
        <span v-if="!event.is_all_day" :class="compact ? 'hidden sm:inline mr-1 opacity-75' : 'mr-1 opacity-75'">
            {{ formatEventTime(event.starts_at, timezone) }}
        </span>
        <Repeat v-if="isRecurring" :class="compact ? 'hidden sm:inline-block h-3 w-3 mr-0.5 align-text-bottom' : 'inline-block h-3 w-3 mr-0.5 align-text-bottom'" />
        <span>{{ event.title }}</span>
    </button>
</template>
