<script setup lang="ts">
import { computed } from 'vue';
import { toLocalDateString, getLocalHour, getLocalMinute, EVENT_COLORS, getEventColor } from '@/lib/calendar';
import type { CalendarEvent } from '@/types/calendar';
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

const days = computed(() => {
    const d = new Date(props.currentDate);
    d.setDate(d.getDate() - d.getDay());
    return Array.from({ length: 7 }, (_, i) => {
        const day = new Date(d);
        day.setDate(day.getDate() + i);
        return day;
    });
});

const todayStr = computed(() => toLocalDateString(new Date(), props.timezone));

function getTimedEvents(date: Date): CalendarEvent[] {
    const key = toLocalDateString(date, props.timezone);
    return (props.eventsByDate.get(key) ?? []).filter(e => !e.is_all_day);
}

function getAllDayEvents(date: Date): CalendarEvent[] {
    const key = toLocalDateString(date, props.timezone);
    return (props.eventsByDate.get(key) ?? []).filter(e => e.is_all_day);
}

function getEventStyle(event: CalendarEvent): Record<string, string> {
    const start = new Date(event.starts_at);
    const end = new Date(event.ends_at);
    const startHour = getLocalHour(start, props.timezone);
    const startMinute = getLocalMinute(start, props.timezone);
    const endHour = getLocalHour(end, props.timezone);
    const endMinute = getLocalMinute(end, props.timezone);

    const top = (startHour * 60 + startMinute);
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

function formatDayHeader(date: Date): string {
    return date.toLocaleDateString(undefined, { weekday: 'short', day: 'numeric' });
}
</script>

<template>
    <div class="border border-border rounded-lg overflow-hidden">
        <!-- Day headers -->
        <div class="grid grid-cols-[60px_repeat(7,1fr)] border-b border-border">
            <div class="border-r border-border" />
            <div
                v-for="day in days"
                :key="day.toISOString()"
                :class="[
                    'text-center py-2 text-sm font-medium border-r border-border',
                    toLocalDateString(day, timezone) === todayStr ? 'bg-primary/10 text-primary' : 'text-muted-foreground',
                ]"
            >
                {{ formatDayHeader(day) }}
            </div>
        </div>

        <!-- All-day strip -->
        <div class="grid grid-cols-[60px_repeat(7,1fr)] border-b border-border">
            <div class="border-r border-border px-1 py-1 text-xs text-muted-foreground text-right">
                all-day
            </div>
            <div
                v-for="day in days"
                :key="'allday-' + day.toISOString()"
                class="border-r border-border p-0.5 min-h-[28px]"
            >
                <CalendarEventChip
                    v-for="event in getAllDayEvents(day)"
                    :key="event.id"
                    :event="event"
                    :timezone="timezone"
                    compact
                    @click="$emit('select-event', event)"
                />
            </div>
        </div>

        <!-- Time grid -->
        <div class="overflow-y-auto max-h-[600px]">
            <div class="grid grid-cols-[60px_repeat(7,1fr)] relative" style="height: 1440px;">
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

                <!-- Day columns -->
                <div
                    v-for="day in days"
                    :key="'col-' + day.toISOString()"
                    class="border-r border-border relative"
                    @click="$emit('select-date', toLocalDateString(day, timezone))"
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
                        v-for="event in getTimedEvents(day)"
                        :key="event.id"
                        :class="[
                            'absolute left-0.5 right-0.5 rounded-md px-1.5 py-0.5 text-xs font-medium overflow-hidden cursor-pointer border transition-opacity hover:opacity-80 z-10',
                            EVENT_COLORS[getEventColor(event)]?.bg ?? EVENT_COLORS.blue.bg,
                            EVENT_COLORS[getEventColor(event)]?.text ?? EVENT_COLORS.blue.text,
                            EVENT_COLORS[getEventColor(event)]?.border ?? EVENT_COLORS.blue.border,
                        ]"
                        :style="getEventStyle(event)"
                        @click.stop="$emit('select-event', event)"
                    >
                        <div class="truncate">{{ event.title }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
