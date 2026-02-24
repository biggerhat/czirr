<script setup lang="ts">
import { computed, ref } from 'vue';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { toLocalDateString, formatEventDateFull } from '@/lib/calendar';
import type { CalendarEvent } from '@/types/calendar';
import CalendarEventChip from './CalendarEventChip.vue';

const MAX_VISIBLE = 3;

const props = defineProps<{
    date: Date;
    events: CalendarEvent[];
    timezone: string;
    isCurrentMonth: boolean;
}>();

const emit = defineEmits<{
    'select-event': [event: CalendarEvent];
    'select-date': [date: string];
}>();

const dateStr = computed(() => toLocalDateString(props.date, props.timezone));
const dayNumber = computed(() => props.date.getDate());
const isToday = computed(() => dateStr.value === toLocalDateString(new Date(), props.timezone));
const visibleEvents = computed(() => props.events.slice(0, MAX_VISIBLE));
const overflowCount = computed(() => Math.max(0, props.events.length - MAX_VISIBLE));

const popoverOpen = ref(false);
const dateLabel = computed(() => formatEventDateFull(props.date.toISOString(), props.timezone));

function selectEventFromPopover(event: CalendarEvent) {
    popoverOpen.value = false;
    emit('select-event', event);
}
</script>

<template>
    <div
        :class="[
            'min-h-[100px] sm:min-h-[140px] border-b border-r border-border p-0.5 sm:p-1 cursor-pointer transition-colors hover:bg-muted/50',
            !isCurrentMonth && 'bg-muted/30 text-muted-foreground',
        ]"
        @click="$emit('select-date', dateStr)"
    >
        <div class="flex justify-end mb-0.5">
            <span
                :class="[
                    'inline-flex h-5 w-5 sm:h-6 sm:w-6 items-center justify-center rounded-full text-[10px] sm:text-xs font-medium',
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
            <Popover v-if="overflowCount > 0" v-model:open="popoverOpen">
                <PopoverTrigger as-child>
                    <button
                        class="w-full text-left text-[10px] sm:text-xs text-muted-foreground hover:text-foreground px-1"
                        @click.stop
                    >
                        +{{ overflowCount }} more
                    </button>
                </PopoverTrigger>
                <PopoverContent class="w-72 p-0" side="bottom" align="start" @click.stop>
                    <div class="px-3 pt-3 pb-2">
                        <p class="text-sm font-medium">{{ dateLabel }}</p>
                    </div>
                    <div class="max-h-64 overflow-y-auto px-3 pb-3 space-y-1">
                        <CalendarEventChip
                            v-for="event in events"
                            :key="event.id"
                            :event="event"
                            :timezone="timezone"
                            @click="selectEventFromPopover(event)"
                        />
                    </div>
                </PopoverContent>
            </Popover>
        </div>
    </div>
</template>
