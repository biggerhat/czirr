<script setup lang="ts">
import { computed } from 'vue';
import { formatEventTime, formatEventDateFull, EVENT_COLORS, getEventColor, toLocalDateString } from '@/lib/calendar';
import type { CalendarEvent } from '@/types/calendar';

const props = defineProps<{
    currentDate: Date;
    eventsByDate: Map<string, CalendarEvent[]>;
    timezone: string;
}>();

defineEmits<{
    'select-event': [event: CalendarEvent];
}>();

const todayStr = computed(() => toLocalDateString(new Date(), props.timezone));

const sortedDates = computed(() => {
    const entries: { dateKey: string; events: CalendarEvent[] }[] = [];
    const sorted = [...props.eventsByDate.entries()].sort(([a], [b]) => a.localeCompare(b));

    for (const [dateKey, events] of sorted) {
        if (events.length > 0) {
            entries.push({ dateKey, events: [...events].sort((a, b) => a.starts_at.localeCompare(b.starts_at)) });
        }
    }
    return entries;
});

function formatDateHeading(dateKey: string): string {
    const [year, month, day] = dateKey.split('-').map(Number);
    const date = new Date(year, month - 1, day);
    return formatEventDateFull(date.toISOString(), props.timezone);
}
</script>

<template>
    <div class="space-y-6">
        <div v-if="sortedDates.length === 0" class="text-center py-12 text-muted-foreground">
            No events in this period.
        </div>

        <div v-for="{ dateKey, events } in sortedDates" :key="dateKey">
            <h3
                :class="[
                    'text-sm font-semibold mb-2 pb-1 border-b border-border',
                    dateKey === todayStr ? 'text-primary' : 'text-foreground',
                ]"
            >
                {{ formatDateHeading(dateKey) }}
                <span v-if="dateKey === todayStr" class="ml-2 text-xs font-normal text-primary">(Today)</span>
            </h3>
            <div class="space-y-1.5">
                <button
                    v-for="event in events"
                    :key="event.id"
                    :class="[
                        'w-full flex items-start gap-3 rounded-lg px-3 py-2 text-left transition-colors hover:bg-muted/50 border',
                        EVENT_COLORS[getEventColor(event)]?.border ?? EVENT_COLORS.blue.border,
                    ]"
                    @click="$emit('select-event', event)"
                >
                    <div :class="['w-2.5 h-2.5 rounded-full mt-1.5 shrink-0', EVENT_COLORS[getEventColor(event)]?.dot ?? EVENT_COLORS.blue.dot]" />
                    <div class="min-w-0 flex-1">
                        <div class="font-medium text-sm truncate">{{ event.title }}</div>
                        <div class="text-xs text-muted-foreground">
                            <template v-if="event.is_all_day">All day</template>
                            <template v-else>
                                {{ formatEventTime(event.starts_at, timezone) }} – {{ formatEventTime(event.ends_at, timezone) }}
                            </template>
                        </div>
                    </div>
                    <div v-if="event.attendees.length > 0" class="text-xs text-muted-foreground shrink-0">
                        {{ event.attendees.length }} attendee{{ event.attendees.length > 1 ? 's' : '' }}
                    </div>
                </button>
            </div>
        </div>
    </div>
</template>
