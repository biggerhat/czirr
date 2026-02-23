<script setup lang="ts">
import { ref, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { Pencil, Trash2, Clock, Users, Repeat, Receipt, DollarSign } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetFooter,
} from '@/components/ui/sheet';
import RecurrenceEditModeDialog from '@/components/calendar/RecurrenceEditModeDialog.vue';
import type { CalendarEvent, EditMode } from '@/types/calendar';
import { EVENT_COLORS, getEventColor, formatEventTime, formatEventDateFull } from '@/lib/calendar';
import { humanReadableRRule } from '@/lib/recurrence';

const props = defineProps<{
    event: CalendarEvent | null;
    open: boolean;
    timezone: string;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    edit: [event: CalendarEvent, editMode: EditMode | null, occurrenceStart: string | null];
    delete: [eventId: number | string, isRecurring: boolean];
    'log-expense': [event: CalendarEvent];
    'add-income': [event: CalendarEvent];
}>();

const page = usePage();
const isOwner = computed(() => props.event?.user_id === (page.props.auth as any)?.user?.id);
const colors = computed(() => {
    if (!props.event) return EVENT_COLORS.blue;
    return EVENT_COLORS[getEventColor(props.event)] ?? EVENT_COLORS.blue;
});

const isRecurringEvent = computed(() => {
    if (!props.event) return false;
    return !!props.event.rrule || !!props.event.is_occurrence;
});

const recurrenceDescription = computed(() => {
    if (!props.event?.rrule) return '';
    return humanReadableRRule(props.event.rrule);
});

const showEditModeDialog = ref(false);
const showDeleteModeDialog = ref(false);
const showDeleteConfirmDialog = ref(false);
const isDeleting = ref(false);

function handleEditClick() {
    if (isRecurringEvent.value) {
        showEditModeDialog.value = true;
    } else {
        emit('edit', props.event!, null, null);
    }
}

function handleEditModeConfirm(mode: EditMode) {
    showEditModeDialog.value = false;
    const occStart = props.event?.occurrence_start ?? null;
    emit('edit', props.event!, mode, occStart);
}

function handleDeleteClick() {
    if (isRecurringEvent.value) {
        showDeleteModeDialog.value = true;
    } else {
        showDeleteConfirmDialog.value = true;
    }
}

function confirmDelete() {
    showDeleteConfirmDialog.value = false;
    handleDelete('all');
}

function handleDeleteModeConfirm(mode: EditMode) {
    showDeleteModeDialog.value = false;
    handleDelete(mode);
}

async function handleDelete(deleteMode: string) {
    if (!props.event) return;
    isDeleting.value = true;

    // Resolve event ID for API call (use master for occurrences)
    const eventId = props.event.master_event_id ?? props.event.id;

    try {
        const response = await fetch(`/events/${eventId}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': decodeURIComponent(
                    document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
                ),
            },
            body: JSON.stringify({
                delete_mode: deleteMode,
                occurrence_start: props.event.occurrence_start ?? null,
                timezone: props.timezone,
            }),
        });

        if (response.ok) {
            emit('delete', props.event.id, isRecurringEvent.value);
        }
    } finally {
        isDeleting.value = false;
    }
}
</script>

<template>
    <Sheet :open="open" @update:open="$emit('update:open', $event)">
        <SheetContent side="right" class="overflow-y-auto">
            <SheetHeader>
                <div class="flex items-center gap-2">
                    <div :class="['w-3 h-3 rounded-full shrink-0', colors.dot]" />
                    <SheetTitle class="text-lg">{{ event?.title }}</SheetTitle>
                </div>
                <SheetDescription v-if="event?.description">
                    {{ event.description }}
                </SheetDescription>
            </SheetHeader>

            <div v-if="event" class="space-y-4 px-4">
                <div class="flex items-start gap-3 text-sm">
                    <Clock class="h-4 w-4 mt-0.5 text-muted-foreground shrink-0" />
                    <div>
                        <div>{{ formatEventDateFull(event.starts_at, timezone) }}</div>
                        <div v-if="event.is_all_day" class="text-muted-foreground">All day</div>
                        <template v-else>
                            <div class="text-muted-foreground">
                                {{ formatEventTime(event.starts_at, timezone) }} – {{ formatEventTime(event.ends_at, timezone) }}
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Recurrence info -->
                <div v-if="recurrenceDescription" class="flex items-start gap-3 text-sm">
                    <Repeat class="h-4 w-4 mt-0.5 text-muted-foreground shrink-0" />
                    <div class="text-muted-foreground">{{ recurrenceDescription }}</div>
                </div>

                <div v-if="event.family_members && event.family_members.length > 0" class="flex items-start gap-3 text-sm">
                    <Users class="h-4 w-4 mt-0.5 text-muted-foreground shrink-0" />
                    <div class="space-y-0.5">
                        <div v-for="fm in event.family_members" :key="fm.id" class="flex items-center gap-2 text-sm">
                            <div :class="['w-2.5 h-2.5 rounded-full shrink-0', EVENT_COLORS[fm.color]?.dot ?? 'bg-blue-500']" />
                            <span>{{ fm.nickname ?? fm.name }}</span>
                        </div>
                    </div>
                </div>

                <div class="flex items-start gap-3 text-sm">
                    <Users class="h-4 w-4 mt-0.5 text-muted-foreground shrink-0" />
                    <div>
                        <div class="text-muted-foreground">Created by {{ event.owner.name }}</div>
                        <div v-if="event.attendees.length > 0" class="space-y-0.5 mt-1">
                            <div v-for="a in event.attendees" :key="a.id" class="text-sm">
                                {{ a.name }}
                                <span class="text-xs text-muted-foreground">({{ a.pivot?.status ?? 'pending' }})</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <SheetFooter v-if="isOwner">
                <div class="space-y-2 w-full">
                    <div class="flex gap-2">
                        <Button variant="outline" size="sm" class="flex-1" @click="handleEditClick">
                            <Pencil class="h-3.5 w-3.5 mr-1" />
                            Edit
                        </Button>
                        <Button variant="destructive" size="sm" class="flex-1" @click="handleDeleteClick">
                            <Trash2 class="h-3.5 w-3.5 mr-1" />
                            Delete
                        </Button>
                    </div>
                </div>
            </SheetFooter>
        </SheetContent>
    </Sheet>

    <Dialog :open="showDeleteConfirmDialog" @update:open="showDeleteConfirmDialog = $event">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Delete event?</DialogTitle>
                <DialogDescription>
                    This will permanently delete "{{ event?.title }}". This cannot be undone.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2">
                <Button variant="outline" @click="showDeleteConfirmDialog = false">Cancel</Button>
                <Button variant="destructive" :disabled="isDeleting" @click="confirmDelete">
                    {{ isDeleting ? 'Deleting...' : 'Delete' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <RecurrenceEditModeDialog
        :open="showEditModeDialog"
        action="edit"
        @update:open="showEditModeDialog = $event"
        @confirm="handleEditModeConfirm"
        @cancel="showEditModeDialog = false"
    />

    <RecurrenceEditModeDialog
        :open="showDeleteModeDialog"
        action="delete"
        @update:open="showDeleteModeDialog = $event"
        @confirm="handleDeleteModeConfirm"
        @cancel="showDeleteModeDialog = false"
    />
</template>
