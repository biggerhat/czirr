<script setup lang="ts">
import { computed } from 'vue';
import type { CalendarEvent } from '@/types/calendar';
import { toLocalDateString, getLocalHour, getLocalMinute, EVENT_COLORS, getEventColor } from '@/lib/calendar';
import CalendarEventChip from './CalendarEventChip.vue';

const props = defineProps<{
    currentDate: Date;
    eventsByDate: Map<string, CalendarEvent[]>;
    timezone: string;
}>();

defineEmits<{
    'select-event': [event: CalendarEvent];
    'select-date': [date: string];
}>();

const hours = Array.from({ length: 24 }, (_, i) => i);

const dateKey = computed(() => toLocalDateString(props.currentDate, props.timezone));
const todayStr = computed(() => toLocalDateString(new Date(), props.timezone));
const isToday = computed(() => dateKey.value === todayStr.value);

const allDayEvents = computed(() =>
    (props.eventsByDate.get(dateKey.value) ?? []).filter(e => e.is_all_day),
);
const timedEvents = computed(() =>
    (props.eventsByDate.get(dateKey.value) ?? []).filter(e => !e.is_all_day),
);

function getEventStyle(event: CalendarEvent): Record<string, string> {
    const start = new Date(event.starts_at);
    const end = new Date(event.ends_at);
    const startHour = getLocalHour(start, props.timezone);
    const startMinute = getLocalMinute(start, props.timezone);
    const endHour = getLocalHour(end, props.timezone);
    const endMinute = getLocalMinute(end, props.timezone);

    const top = startHour * 60 + startMinute;
    const duration = Math.max(20, (endHour * 60 + endMinute) - top);

    return {
        top: `${top}px`,
        height: `${duration}px`,
    };
}

function formatHour(h: number): string {
    if (h === 0) return '12 AM';
    if (h < 12) return `${h} AM`;
    if (h === 12) return '12 PM';
    return `${h - 12} PM`;
}
</script>

<template>
    <div class="border border-border rounded-lg overflow-hidden">
        <!-- Day header -->
        <div
            :class="[
                'text-center py-3 text-sm font-semibold border-b border-border',
                isToday ? 'bg-primary/10 text-primary' : '',
            ]"
        >
            {{ currentDate.toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric' }) }}
        </div>

        <!-- All-day strip -->
        <div v-if="allDayEvents.length > 0" class="border-b border-border p-1.5 space-y-0.5">
            <div class="text-xs text-muted-foreground mb-1">All Day</div>
            <CalendarEventChip
                v-for="event in allDayEvents"
                :key="event.id"
                :event="event"
                :timezone="timezone"
                compact
                @click="$emit('select-event', event)"
            />
        </div>

        <!-- Time grid -->
        <div class="overflow-y-auto max-h-[600px]">
            <div class="grid grid-cols-[60px_1fr] relative" style="height: 1440px;">
                <!-- Hour labels -->
                <div class="border-r border-border relative">
                    <div
                        v-for="hour in hours"
                        :key="hour"
                        class="absolute w-full text-right pr-2 text-xs text-muted-foreground"
                        :style="{ top: `${hour * 60}px`, height: '60px' }"
                    >
                        <span class="-translate-y-1/2 inline-block">{{ formatHour(hour) }}</span>
                    </div>
                </div>

                <!-- Event column -->
                <div
                    class="relative"
                    @click="$emit('select-date', dateKey)"
                >
                    <!-- Hour lines -->
                    <div
                        v-for="hour in hours"
                        :key="hour"
                        class="absolute w-full border-b border-border/50"
                        :style="{ top: `${hour * 60}px`, height: '60px' }"
                    />
                    <!-- Events -->
                    <div
                        v-for="event in timedEvents"
                        :key="event.id"
                        :class="[
                            'absolute left-1 right-1 rounded-md px-2 py-1 text-xs font-medium overflow-hidden cursor-pointer border transition-opacity hover:opacity-80 z-10',
                            EVENT_COLORS[getEventColor(event)]?.bg ?? EVENT_COLORS.blue.bg,
                            EVENT_COLORS[getEventColor(event)]?.text ?? EVENT_COLORS.blue.text,
                            EVENT_COLORS[getEventColor(event)]?.border ?? EVENT_COLORS.blue.border,
                        ]"
                        :style="getEventStyle(event)"
                        @click.stop="$emit('select-event', event)"
                    >
                        <div class="truncate font-semibold">{{ event.title }}</div>
                        <div v-if="event.description" class="truncate text-[10px] opacity-75">{{ event.description }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
