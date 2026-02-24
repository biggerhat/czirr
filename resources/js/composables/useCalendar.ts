import { ref, computed, watch } from 'vue';
import { toLocalDateString } from '@/lib/calendar';
import type { CalendarEvent, CalendarView, EditMode } from '@/types/calendar';

const HIDDEN_SOURCES_KEY = 'calendar-hidden-sources';
const HIDDEN_EVENT_TYPES_KEY = 'calendar-hidden-event-types';

function loadHiddenSources(): Set<string> {
    try {
        const stored = localStorage.getItem(HIDDEN_SOURCES_KEY);
        return stored ? new Set(JSON.parse(stored)) : new Set();
    } catch {
        return new Set();
    }
}

function loadHiddenEventTypes(): Set<number> {
    try {
        const stored = localStorage.getItem(HIDDEN_EVENT_TYPES_KEY);
        return stored ? new Set(JSON.parse(stored)) : new Set();
    } catch {
        return new Set();
    }
}

export function useCalendar() {
    const view = ref<CalendarView>('month');
    const currentDate = ref(new Date());
    const events = ref<CalendarEvent[]>([]);
    const isLoading = ref(false);
    const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
    const hiddenSources = ref<Set<string>>(loadHiddenSources());
    const hiddenEventTypes = ref<Set<number>>(loadHiddenEventTypes());

    function toggleSource(source: string) {
        const next = new Set(hiddenSources.value);
        if (next.has(source)) { next.delete(source); } else { next.add(source); }
        hiddenSources.value = next;
        localStorage.setItem(HIDDEN_SOURCES_KEY, JSON.stringify([...next]));
    }

    function toggleEventType(id: number) {
        const next = new Set(hiddenEventTypes.value);
        if (next.has(id)) { next.delete(id); } else { next.add(id); }
        hiddenEventTypes.value = next;
        localStorage.setItem(HIDDEN_EVENT_TYPES_KEY, JSON.stringify([...next]));
    }

    // Modal states
    const showEventModal = ref(false);
    const showEventDetail = ref(false);
    const editingEvent = ref<CalendarEvent | null>(null);
    const selectedEvent = ref<CalendarEvent | null>(null);
    const defaultDate = ref<string | null>(null);
    const editMode = ref<EditMode | null>(null);
    const occurrenceStart = ref<string | null>(null);

    const visibleRange = computed(() => {
        const d = currentDate.value;
        let start: Date;
        let end: Date;

        if (view.value === 'month') {
            const firstOfMonth = new Date(d.getFullYear(), d.getMonth(), 1);
            start = new Date(firstOfMonth);
            start.setDate(start.getDate() - start.getDay());
            // Always cover 6 weeks (42 days) to match the month grid
            end = new Date(start);
            end.setDate(end.getDate() + 41);
        } else if (view.value === 'week') {
            start = new Date(d);
            start.setDate(start.getDate() - start.getDay());
            end = new Date(start);
            end.setDate(end.getDate() + 6);
        } else if (view.value === 'day') {
            start = new Date(d);
            end = new Date(d);
        } else {
            // agenda: show 30 days from current date
            start = new Date(d);
            end = new Date(d);
            end.setDate(end.getDate() + 30);
        }

        start.setHours(0, 0, 0, 0);
        end.setHours(23, 59, 59, 999);

        return { start, end };
    });

    const calendarTitle = computed(() => {
        const d = currentDate.value;
        if (view.value === 'month') {
            return d.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
        } else if (view.value === 'week') {
            const { start, end } = visibleRange.value;
            const sameMonth = start.getMonth() === end.getMonth();
            if (sameMonth) {
                return `${start.toLocaleDateString(undefined, { month: 'long', day: 'numeric' })} – ${end.toLocaleDateString(undefined, { day: 'numeric', year: 'numeric' })}`;
            }
            return `${start.toLocaleDateString(undefined, { month: 'short', day: 'numeric' })} – ${end.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })}`;
        } else if (view.value === 'day') {
            return d.toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
        } else {
            return `Agenda – ${d.toLocaleDateString(undefined, { month: 'long', year: 'numeric' })}`;
        }
    });

    const eventsByDate = computed(() => {
        const map = new Map<string, CalendarEvent[]>();
        for (const event of events.value) {
            if (event.source && hiddenSources.value.has(event.source)) continue;
            if (event.event_type_id && hiddenEventTypes.value.has(event.event_type_id)) continue;
            const start = new Date(event.starts_at);
            const end = new Date(event.ends_at);

            // For multi-day events, add to each day
            const day = new Date(start);
            day.setHours(0, 0, 0, 0);
            const endDay = new Date(end);
            endDay.setHours(0, 0, 0, 0);

            while (day <= endDay) {
                const key = toLocalDateString(day, timezone);
                if (!map.has(key)) {
                    map.set(key, []);
                }
                map.get(key)!.push(event);
                day.setDate(day.getDate() + 1);
            }
        }
        return map;
    });

    function goToday() {
        currentDate.value = new Date();
    }

    function goPrev() {
        const d = new Date(currentDate.value);
        if (view.value === 'month') {
            d.setMonth(d.getMonth() - 1);
        } else if (view.value === 'week') {
            d.setDate(d.getDate() - 7);
        } else if (view.value === 'day') {
            d.setDate(d.getDate() - 1);
        } else {
            d.setDate(d.getDate() - 30);
        }
        currentDate.value = d;
    }

    function goNext() {
        const d = new Date(currentDate.value);
        if (view.value === 'month') {
            d.setMonth(d.getMonth() + 1);
        } else if (view.value === 'week') {
            d.setDate(d.getDate() + 7);
        } else if (view.value === 'day') {
            d.setDate(d.getDate() + 1);
        } else {
            d.setDate(d.getDate() + 30);
        }
        currentDate.value = d;
    }

    function openCreateModal(date?: string) {
        editingEvent.value = null;
        editMode.value = null;
        occurrenceStart.value = null;
        defaultDate.value = date ?? null;
        showEventModal.value = true;
    }

    function openEditModal(event: CalendarEvent, mode?: EditMode, occStart?: string) {
        editingEvent.value = event;
        editMode.value = mode ?? null;
        occurrenceStart.value = occStart ?? null;
        defaultDate.value = null;
        showEventModal.value = true;
    }

    function openEventDetail(event: CalendarEvent) {
        selectedEvent.value = event;
        showEventDetail.value = true;
    }

    function closeModals() {
        showEventModal.value = false;
        showEventDetail.value = false;
        // Delay clearing event data so the Sheet close animation
        // can still render the content while sliding out.
        setTimeout(() => {
            // Only clear if modals are still closed (avoid race with a quick re-open)
            if (!showEventModal.value) {
                editingEvent.value = null;
                defaultDate.value = null;
                editMode.value = null;
                occurrenceStart.value = null;
            }
            if (!showEventDetail.value) {
                selectedEvent.value = null;
            }
        }, 350);
    }

    async function loadEvents() {
        isLoading.value = true;
        try {
            const { start, end } = visibleRange.value;
            const params = new URLSearchParams({
                start: start.toISOString(),
                end: end.toISOString(),
                timezone,
            });
            const response = await fetch(`/events?${params}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            if (response.ok) {
                const data: CalendarEvent[] = await response.json();

                // Hydrate recurring occurrences from their master event
                const masterMap = new Map<number, CalendarEvent>();
                for (const e of data) {
                    if (e.rrule && !e.is_occurrence) masterMap.set(e.id as number, e);
                }
                for (const e of data) {
                    if (e.is_occurrence && e.master_event_id && !e.owner) {
                        const master = masterMap.get(e.master_event_id);
                        if (master) {
                            e.owner = master.owner;
                            e.attendees = master.attendees;
                            e.family_members = master.family_members;
                            e.event_type_id = e.event_type_id ?? master.event_type_id;
                            e.event_type = e.event_type ?? master.event_type;
                        }
                    }
                }

                events.value = data;
            }
        } finally {
            isLoading.value = false;
        }
    }

    function onEventSaved(event: CalendarEvent, isRecurring?: boolean) {
        if (isRecurring) {
            closeModals();
            loadEvents();
            return;
        }
        const idx = events.value.findIndex(e => e.id === event.id);
        if (idx >= 0) {
            events.value[idx] = event;
        } else {
            events.value.push(event);
        }
        closeModals();
    }

    function onEventDeleted(eventId: number | string, isRecurring?: boolean) {
        if (isRecurring) {
            closeModals();
            loadEvents();
            return;
        }
        events.value = events.value.filter(e => e.id !== eventId);
        closeModals();
    }

    // Auto-load events when visible range changes (skip if boundaries unchanged)
    let lastFetchedRange = { start: '', end: '' };

    watch(visibleRange, () => {
        const { start, end } = visibleRange.value;
        const startStr = start.toISOString();
        const endStr = end.toISOString();
        if (startStr === lastFetchedRange.start && endStr === lastFetchedRange.end) return;
        lastFetchedRange = { start: startStr, end: endStr };
        loadEvents();
    }, { immediate: true });

    return {
        view,
        currentDate,
        events,
        isLoading,
        timezone,
        visibleRange,
        calendarTitle,
        eventsByDate,
        showEventModal,
        showEventDetail,
        editingEvent,
        selectedEvent,
        defaultDate,
        editMode,
        occurrenceStart,
        goToday,
        goPrev,
        goNext,
        openCreateModal,
        openEditModal,
        openEventDetail,
        closeModals,
        loadEvents,
        onEventSaved,
        onEventDeleted,
        hiddenSources,
        toggleSource,
        hiddenEventTypes,
        toggleEventType,
    };
}
