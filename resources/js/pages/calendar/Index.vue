<script setup lang="ts">
import { ref } from 'vue';
import CalendarAgendaView from '@/components/calendar/CalendarAgendaView.vue';
import CalendarDayView from '@/components/calendar/CalendarDayView.vue';
import CalendarEventDetail from '@/components/calendar/CalendarEventDetail.vue';
import CalendarEventModal from '@/components/calendar/CalendarEventModal.vue';
import type { EntryType } from '@/components/calendar/CalendarEventModal.vue';
import CalendarMonthView from '@/components/calendar/CalendarMonthView.vue';
import CalendarToolbar from '@/components/calendar/CalendarToolbar.vue';
import CalendarWeekView from '@/components/calendar/CalendarWeekView.vue';
import { useCalendar } from '@/composables/useCalendar';
import AppLayout from '@/layouts/AppLayout.vue';
import { toLocalDateString } from '@/lib/calendar';
import type { BreadcrumbItem } from '@/types';
import type { BudgetCategory } from '@/types/budgeting';
import type { FamilyMember, CalendarEvent, EditMode, EventType } from '@/types/calendar';

defineProps<{
    familyMembers: FamilyMember[];
    budgetCategories: BudgetCategory[];
    eventTypes: EventType[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Calendar' },
];

const {
    view,
    currentDate,
    timezone,
    calendarTitle,
    eventsByDate,
    isLoading,
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
    loadEvents,
    onEventSaved,
    onEventDeleted,
    hiddenSources,
    toggleSource,
    hiddenEventTypes,
    toggleEventType,
} = useCalendar();

const defaultEntryType = ref<EntryType>('event');

function handleSelectDate(date: string) {
    defaultEntryType.value = 'event';
    openCreateModal(date);
}

function handleNewEvent() {
    defaultEntryType.value = 'event';
    openCreateModal();
}

function handleEditFromDetail(event: CalendarEvent, mode: EditMode | null, occStart: string | null) {
    showEventDetail.value = false;
    setTimeout(() => {
        defaultEntryType.value = 'event';
        openEditModal(event, mode ?? undefined, occStart ?? undefined);
    }, 350);
}

function handleLogExpense(event: CalendarEvent) {
    showEventDetail.value = false;
    const eventDate = toLocalDateString(new Date(event.starts_at), timezone);
    setTimeout(() => {
        defaultEntryType.value = 'expense';
        defaultDate.value = eventDate;
        editingEvent.value = null;
        showEventModal.value = true;
    }, 350);
}

function handleAddIncome() {
    showEventDetail.value = false;
    setTimeout(() => {
        defaultEntryType.value = 'income';
        defaultDate.value = null;
        editingEvent.value = null;
        showEventModal.value = true;
    }, 350);
}

function handleBudgetSaved() {
    // Reload calendar events since bills/incomes create linked events
    loadEvents();
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <CalendarToolbar
                :title="calendarTitle"
                :view="view"
                :hidden-sources="hiddenSources"
                :event-types="eventTypes"
                :hidden-event-types="hiddenEventTypes"
                @prev="goPrev"
                @next="goNext"
                @today="goToday"
                @update:view="view = $event"
                @new-event="handleNewEvent"
                @toggle-source="toggleSource"
                @toggle-event-type="toggleEventType"
            />

            <div v-if="isLoading" class="flex items-center justify-center py-12">
                <div class="h-6 w-6 animate-spin rounded-full border-2 border-primary border-t-transparent" />
            </div>

            <template v-else>
                <CalendarMonthView
                    v-if="view === 'month'"
                    :current-date="currentDate"
                    :events-by-date="eventsByDate"
                    :timezone="timezone"
                    @select-event="openEventDetail"
                    @select-date="handleSelectDate"
                />

                <CalendarWeekView
                    v-if="view === 'week'"
                    :current-date="currentDate"
                    :events-by-date="eventsByDate"
                    :timezone="timezone"
                    @select-event="openEventDetail"
                    @select-date="handleSelectDate"
                />

                <CalendarDayView
                    v-if="view === 'day'"
                    :current-date="currentDate"
                    :events-by-date="eventsByDate"
                    :timezone="timezone"
                    @select-event="openEventDetail"
                    @select-date="handleSelectDate"
                />

                <CalendarAgendaView
                    v-if="view === 'agenda'"
                    :current-date="currentDate"
                    :events-by-date="eventsByDate"
                    :timezone="timezone"
                    @select-event="openEventDetail"
                />
            </template>

            <CalendarEventModal
                :event="editingEvent"
                :open="showEventModal"
                :timezone="timezone"
                :family-members="familyMembers"
                :categories="budgetCategories"
                :event-types="eventTypes"
                :default-date="defaultDate"
                :default-entry-type="defaultEntryType"
                :edit-mode="editMode"
                :occurrence-start="occurrenceStart"
                @update:open="showEventModal = $event"
                @saved="onEventSaved"
                @budget-saved="handleBudgetSaved"
            />

            <CalendarEventDetail
                :event="selectedEvent"
                :open="showEventDetail"
                :timezone="timezone"
                @update:open="showEventDetail = $event"
                @edit="handleEditFromDetail"
                @delete="onEventDeleted"
                @log-expense="handleLogExpense"
                @add-income="handleAddIncome"
            />
        </div>
    </AppLayout>
</template>
