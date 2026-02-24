<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { CalendarDays, Check, ChevronLeft, ChevronRight, Grid3X3, Pencil, Plus, Printer, Trash2 } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import CellPicker from '@/components/chores/CellPicker.vue';
import ChoreModal from '@/components/chores/ChoreModal.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useSwipe } from '@/composables/useSwipe';
import AppLayout from '@/layouts/AppLayout.vue';
import { EVENT_COLORS } from '@/lib/calendar';
import type { BreadcrumbItem } from '@/types';
import type { FamilyMember } from '@/types/calendar';
import type { Chore, DayOfWeek } from '@/types/chores';
import { DAY_LABELS } from '@/types/chores';

type ChoreView = 'grid' | 'day';

const DAY_LABELS_FULL: Record<DayOfWeek, string> = {
    0: 'Sunday',
    1: 'Monday',
    2: 'Tuesday',
    3: 'Wednesday',
    4: 'Thursday',
    5: 'Friday',
    6: 'Saturday',
};

const props = defineProps<{
    chores: Chore[];
    familyMembers: FamilyMember[];
    can: {
        create: boolean;
        edit: boolean;
        delete: boolean;
        assign: boolean;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Chores' },
];

const days: DayOfWeek[] = [0, 1, 2, 3, 4, 5, 6];
const view = ref<ChoreView>('grid');

// Day view: group assignments by day → chore
type DayChoreEntry = {
    chore: Chore;
    members: FamilyMember[];
};

const choresByDay = computed(() => {
    const result: Record<DayOfWeek, DayChoreEntry[]> = {
        0: [], 1: [], 2: [], 3: [], 4: [], 5: [], 6: [],
    };

    for (const chore of props.chores) {
        if (!chore.is_active) continue;
        for (const day of days) {
            const dayAssignments = chore.assignments.filter(a => a.day_of_week === day);
            if (dayAssignments.length > 0) {
                result[day].push({
                    chore,
                    members: dayAssignments.map(a => a.family_member),
                });
            }
        }
    }

    return result;
});

// Modal state
const showChoreModal = ref(false);
const editingChore = ref<Chore | null>(null);

function openCreateChore() {
    editingChore.value = null;
    showChoreModal.value = true;
}

function openEditChore(chore: Chore) {
    editingChore.value = chore;
    showChoreModal.value = true;
}

function onSaved() {
    router.reload();
}

// Detail dialog state (day view)
const showDetailDialog = ref(false);
const detailEntry = ref<DayChoreEntry | null>(null);
const detailDay = ref<DayOfWeek>(0);

function openDetail(entry: DayChoreEntry, day: DayOfWeek) {
    detailEntry.value = entry;
    detailDay.value = day;
    showDetailDialog.value = true;
}

const activeChores = computed(() => props.chores.filter(c => c.is_active));

// Detail dialog: computed set of assigned member IDs for the current detail entry
const detailAssignedIds = computed(() => {
    if (!detailEntry.value) return new Set<number>();
    return new Set(detailEntry.value.members.map(m => m.id));
});

const isTogglingAssignment = ref(false);

async function toggleAssignmentFromDetail(choreId: number, familyMemberId: number, day: DayOfWeek) {
    isTogglingAssignment.value = true;
    await toggleAssignment(choreId, familyMemberId, day);
    isTogglingAssignment.value = false;

    // Update the detail entry in-place after reload
    const chore = props.chores.find(c => c.id === choreId);
    if (chore && detailEntry.value) {
        const dayAssignments = chore.assignments.filter(a => a.day_of_week === day);
        detailEntry.value = {
            chore,
            members: dayAssignments.map(a => a.family_member),
        };
    }
}

function editFromDetail() {
    if (!detailEntry.value) return;
    showDetailDialog.value = false;
    openEditChore(detailEntry.value.chore);
}

function deleteFromDetail() {
    if (!detailEntry.value) return;
    showDetailDialog.value = false;
    openDeleteChore(detailEntry.value.chore);
}

function printChart() {
    window.print();
}

function getAssignmentsForCell(chore: Chore, day: DayOfWeek) {
    return chore.assignments.filter(a => a.day_of_week === day);
}

async function toggleAssignment(choreId: number, familyMemberId: number, day: DayOfWeek) {
    await fetch('/chore-assignments/toggle', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': decodeURIComponent(
                document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
            ),
        },
        body: JSON.stringify({
            chore_id: choreId,
            family_member_id: familyMemberId,
            day_of_week: day,
        }),
    });
    router.reload();
}

const showDeleteChoreDialog = ref(false);
const deletingChore = ref<Chore | null>(null);
const isDeletingChore = ref(false);

function openDeleteChore(chore: Chore) {
    deletingChore.value = chore;
    showDeleteChoreDialog.value = true;
}

async function confirmDeleteChore() {
    if (!deletingChore.value) return;
    isDeletingChore.value = true;

    await fetch(`/chores/${deletingChore.value.id}`, {
        method: 'DELETE',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': decodeURIComponent(
                document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
            ),
        },
    });
    isDeletingChore.value = false;
    showDeleteChoreDialog.value = false;
    router.reload();
}

// Mobile day view
const todayDayOfWeek = new Date().getDay() as DayOfWeek;
const selectedDayIndex = ref<DayOfWeek>(todayDayOfWeek);

function prevMobileDay() {
    selectedDayIndex.value = ((selectedDayIndex.value - 1 + 7) % 7) as DayOfWeek;
}

function nextMobileDay() {
    selectedDayIndex.value = ((selectedDayIndex.value + 1) % 7) as DayOfWeek;
}

const { onTouchStart: swipeTouchStart, onTouchEnd: swipeTouchEnd } = useSwipe(nextMobileDay, prevMobileDay);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4" data-print-hide>
            <!-- Header -->
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-lg font-semibold shrink-0">Chore Chart</h2>
                <div class="flex items-center gap-2">
                    <div class="hidden md:flex rounded-lg border border-border overflow-hidden">
                        <button
                            :class="[
                                'flex items-center gap-1 px-3 py-1.5 text-sm font-medium transition-colors',
                                view === 'grid'
                                    ? 'bg-primary text-primary-foreground'
                                    : 'hover:bg-muted text-muted-foreground',
                            ]"
                            @click="view = 'grid'"
                        >
                            <Grid3X3 class="h-3.5 w-3.5" />
                            Grid
                        </button>
                        <button
                            :class="[
                                'flex items-center gap-1 px-3 py-1.5 text-sm font-medium transition-colors',
                                view === 'day'
                                    ? 'bg-primary text-primary-foreground'
                                    : 'hover:bg-muted text-muted-foreground',
                            ]"
                            @click="view = 'day'"
                        >
                            <CalendarDays class="h-3.5 w-3.5" />
                            By Day
                        </button>
                    </div>
                    <Button v-if="chores.length > 0" size="sm" variant="outline" @click="printChart">
                        <Printer class="h-4 w-4 mr-1" />
                        Print
                    </Button>
                    <Button v-if="can.create" size="sm" @click="openCreateChore">
                        <Plus class="h-4 w-4 mr-1" />
                        Add Chore
                    </Button>
                </div>
            </div>

            <!-- Empty state -->
            <div v-if="chores.length === 0" class="rounded-lg border border-dashed p-8 text-center text-muted-foreground">
                No chores yet. Click "Add Chore" to get started.
            </div>

            <template v-else>
                <!-- ===== MOBILE DAY VIEW ===== -->
                <div class="md:hidden space-y-3" @touchstart="swipeTouchStart" @touchend="swipeTouchEnd">
                    <!-- Day strip -->
                    <div class="flex items-center gap-1">
                        <button class="p-1 rounded hover:bg-accent shrink-0" @click="prevMobileDay">
                            <ChevronLeft class="h-4 w-4" />
                        </button>
                        <div class="flex-1 flex gap-0.5">
                            <button
                                v-for="day in days"
                                :key="day"
                                class="flex-1 flex flex-col items-center py-1 rounded-md text-xs transition-colors"
                                :class="day === selectedDayIndex ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'"
                                @click="selectedDayIndex = day"
                            >
                                <span class="font-medium">{{ DAY_LABELS[day] }}</span>
                            </button>
                        </div>
                        <button class="p-1 rounded hover:bg-accent shrink-0" @click="nextMobileDay">
                            <ChevronRight class="h-4 w-4" />
                        </button>
                    </div>

                    <!-- Current day header -->
                    <div class="text-center text-sm font-semibold">{{ DAY_LABELS_FULL[selectedDayIndex] }}</div>

                    <!-- Chore cards -->
                    <div v-if="choresByDay[selectedDayIndex].length === 0" class="rounded-lg border border-dashed p-8 text-center text-muted-foreground">
                        No chores on {{ DAY_LABELS_FULL[selectedDayIndex] }}.
                    </div>
                    <div v-else class="space-y-2">
                        <button
                            v-for="entry in choresByDay[selectedDayIndex]"
                            :key="entry.chore.id"
                            class="w-full text-left rounded-lg border px-4 py-3 transition-colors hover:bg-accent cursor-pointer"
                            @click="openDetail(entry, selectedDayIndex)"
                        >
                            <div class="font-medium">{{ entry.chore.name }}</div>
                            <div v-if="entry.chore.description" class="text-xs text-muted-foreground mt-0.5">{{ entry.chore.description }}</div>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <span
                                    v-for="member in entry.members"
                                    :key="member.id"
                                    class="flex items-center gap-1.5 text-sm text-muted-foreground"
                                >
                                    <span
                                        class="h-2.5 w-2.5 shrink-0 rounded-full"
                                        :class="EVENT_COLORS[member.color]?.dot ?? 'bg-blue-500'"
                                    />
                                    {{ member.nickname || member.name }}
                                </span>
                            </div>
                        </button>
                    </div>

                    <!-- Legend -->
                    <div v-if="familyMembers.length > 0" class="flex flex-wrap items-center gap-3 text-sm text-muted-foreground">
                        <span class="font-medium">Legend:</span>
                        <span
                            v-for="member in familyMembers"
                            :key="member.id"
                            class="flex items-center gap-1.5"
                        >
                            <span
                                class="h-3 w-3 rounded-full"
                                :class="EVENT_COLORS[member.color]?.dot ?? 'bg-blue-500'"
                            />
                            {{ member.nickname || member.name }}
                        </span>
                    </div>
                </div>

                <!-- ===== GRID VIEW (desktop only) ===== -->
                <div v-if="view === 'grid'" class="hidden md:block overflow-x-auto rounded-lg border">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b bg-muted/50">
                                <th class="px-4 py-2 text-left font-medium w-[200px]">Chore</th>
                                <th
                                    v-for="day in days"
                                    :key="day"
                                    class="px-2 py-2 text-center font-medium w-[80px]"
                                >
                                    {{ DAY_LABELS[day] }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="chore in chores"
                                :key="chore.id"
                                class="border-b last:border-b-0"
                                :class="{ 'opacity-50': !chore.is_active }"
                            >
                                <td class="px-4 py-2">
                                    <div class="flex items-center gap-1">
                                        <div class="flex-1 min-w-0">
                                            <div class="font-medium truncate">{{ chore.name }}</div>
                                            <div v-if="chore.description" class="text-xs text-muted-foreground truncate">
                                                {{ chore.description }}
                                            </div>
                                        </div>
                                        <template v-if="can.edit || can.delete">
                                            <Button v-if="can.edit" variant="ghost" size="icon" class="h-8 w-8 shrink-0" @click="openEditChore(chore)">
                                                <Pencil class="h-3 w-3" />
                                            </Button>
                                            <Button v-if="can.delete" variant="ghost" size="icon" class="h-8 w-8 shrink-0 text-destructive" @click="openDeleteChore(chore)">
                                                <Trash2 class="h-3 w-3" />
                                            </Button>
                                        </template>
                                    </div>
                                </td>
                                <td
                                    v-for="day in days"
                                    :key="day"
                                    class="px-1 py-1 text-center"
                                >
                                    <CellPicker
                                        :assignments="getAssignmentsForCell(chore, day)"
                                        :family-members="familyMembers"
                                        :chore-id="chore.id"
                                        :day="day"
                                        :readonly="!can.assign"
                                        @toggle="toggleAssignment(chore.id, $event, day)"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Legend (grid view) -->
                <div v-if="view === 'grid' && familyMembers.length > 0" class="hidden md:flex flex-wrap items-center gap-3 text-sm text-muted-foreground">
                    <span class="font-medium">Legend:</span>
                    <span
                        v-for="member in familyMembers"
                        :key="member.id"
                        class="flex items-center gap-1.5"
                    >
                        <span
                            class="h-3 w-3 rounded-full"
                            :class="EVENT_COLORS[member.color]?.dot ?? 'bg-blue-500'"
                        />
                        {{ member.nickname || member.name }}
                    </span>
                </div>

                <!-- ===== DAY VIEW (desktop only) ===== -->
                <div v-if="view === 'day'" class="hidden md:block border border-border rounded-lg overflow-hidden">
                    <!-- Day headers -->
                    <div class="grid grid-cols-7 border-b border-border">
                        <div
                            v-for="day in days"
                            :key="day"
                            class="text-center py-2 text-sm font-semibold border-r border-border last:border-r-0 bg-muted/50"
                        >
                            {{ DAY_LABELS_FULL[day] }}
                        </div>
                    </div>

                    <!-- Day columns -->
                    <div class="grid grid-cols-7">
                        <div
                            v-for="day in days"
                            :key="day"
                            class="border-r border-border last:border-r-0 min-h-[200px] p-2 space-y-1.5"
                        >
                            <div v-if="choresByDay[day].length === 0" class="text-xs text-muted-foreground/50 text-center pt-4">
                                No chores
                            </div>
                            <button
                                v-for="entry in choresByDay[day]"
                                :key="entry.chore.id"
                                class="w-full text-left rounded-md border px-2.5 py-2 text-sm transition-colors hover:bg-accent cursor-pointer"
                                @click="openDetail(entry, day)"
                            >
                                <div class="font-medium text-sm">{{ entry.chore.name }}</div>
                                <div class="mt-1 space-y-0.5">
                                    <div
                                        v-for="member in entry.members"
                                        :key="member.id"
                                        class="flex items-center gap-1.5 text-xs text-muted-foreground"
                                    >
                                        <span
                                            class="h-2.5 w-2.5 shrink-0 rounded-full"
                                            :class="EVENT_COLORS[member.color]?.dot ?? 'bg-blue-500'"
                                        />
                                        {{ member.nickname || member.name }}
                                    </div>
                                </div>
                            </button>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Detail dialog (day view) -->
            <Dialog :open="showDetailDialog" @update:open="showDetailDialog = $event">
                <DialogContent class="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>{{ detailEntry?.chore.name }}</DialogTitle>
                        <DialogDescription v-if="detailEntry?.chore.description">
                            {{ detailEntry.chore.description }}
                        </DialogDescription>
                    </DialogHeader>
                    <div class="space-y-4 pt-2">
                        <div class="text-sm text-muted-foreground font-medium">
                            Assigned on {{ DAY_LABELS_FULL[detailDay] }}
                        </div>

                        <!-- Assignable member list (can toggle) -->
                        <div v-if="can.assign && detailEntry" class="space-y-1">
                            <button
                                v-for="member in familyMembers"
                                :key="member.id"
                                class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-sm transition-colors hover:bg-accent"
                                :disabled="isTogglingAssignment"
                                @click="toggleAssignmentFromDetail(detailEntry!.chore.id, member.id, detailDay)"
                            >
                                <span
                                    class="h-3 w-3 shrink-0 rounded-full"
                                    :class="EVENT_COLORS[member.color]?.dot ?? 'bg-blue-500'"
                                />
                                <span class="flex-1 text-left truncate">{{ member.nickname || member.name }}</span>
                                <Check v-if="detailAssignedIds.has(member.id)" class="h-3.5 w-3.5 shrink-0 text-emerald-500" />
                            </button>
                        </div>

                        <!-- Read-only member list -->
                        <div v-else-if="detailEntry?.members.length" class="space-y-1.5">
                            <div
                                v-for="member in detailEntry.members"
                                :key="member.id"
                                class="flex items-center gap-2 text-sm"
                            >
                                <span
                                    class="h-3 w-3 shrink-0 rounded-full"
                                    :class="EVENT_COLORS[member.color]?.dot ?? 'bg-blue-500'"
                                />
                                {{ member.nickname || member.name }}
                            </div>
                        </div>
                        <div v-else class="text-sm text-muted-foreground italic">
                            No one assigned.
                        </div>
                    </div>
                    <DialogFooter v-if="can.edit || can.delete" class="gap-2">
                        <Button v-if="can.edit" variant="outline" size="sm" @click="editFromDetail">
                            <Pencil class="h-3.5 w-3.5 mr-1" />
                            Edit
                        </Button>
                        <Button v-if="can.delete" variant="destructive" size="sm" @click="deleteFromDetail">
                            <Trash2 class="h-3.5 w-3.5 mr-1" />
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Delete chore dialog -->
            <Dialog :open="showDeleteChoreDialog" @update:open="showDeleteChoreDialog = $event">
                <DialogContent class="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Delete chore?</DialogTitle>
                        <DialogDescription>
                            This will permanently delete "{{ deletingChore?.name }}" and all its assignments. This cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter class="gap-2">
                        <Button variant="outline" @click="showDeleteChoreDialog = false">Cancel</Button>
                        <Button variant="destructive" :disabled="isDeletingChore" @click="confirmDeleteChore">
                            {{ isDeletingChore ? 'Deleting...' : 'Delete' }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Modal -->
            <ChoreModal
                :chore="editingChore"
                :open="showChoreModal"
                @update:open="showChoreModal = $event"
                @saved="onSaved"
            />
        </div>
        <!-- ===== PRINT-ONLY CONTENT ===== -->
        <div class="print-only">
            <h1 class="text-2xl font-bold text-center mb-6">Weekly Chore Chart</h1>

            <!-- Day-view calendar -->
            <table class="w-full border-collapse border border-black text-xs mb-8">
                <thead>
                    <tr>
                        <th
                            v-for="day in days"
                            :key="day"
                            class="border border-black px-2 py-1.5 text-center font-bold bg-gray-100"
                            :style="{ width: `${100 / 7}%` }"
                        >
                            {{ DAY_LABELS_FULL[day] }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td
                            v-for="day in days"
                            :key="day"
                            class="border border-black px-1.5 py-1.5 align-top"
                        >
                            <div
                                v-for="entry in choresByDay[day]"
                                :key="entry.chore.id"
                                class="mb-1.5 last:mb-0"
                            >
                                <div class="font-semibold">{{ entry.chore.name }}</div>
                                <div
                                    v-for="member in entry.members"
                                    :key="member.id"
                                    class="pl-2 text-gray-600"
                                >
                                    &bull; {{ member.nickname || member.name }}
                                </div>
                            </div>
                            <div v-if="choresByDay[day].length === 0" class="text-gray-400 italic">
                                &mdash;
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Chore descriptions list -->
            <h2 class="text-lg font-bold mb-3 border-b border-black pb-1">Chore Descriptions</h2>
            <div class="space-y-2 text-sm">
                <div v-for="chore in activeChores" :key="chore.id">
                    <span class="font-semibold">{{ chore.name }}</span>
                    <template v-if="chore.description">
                        &mdash; {{ chore.description }}
                    </template>
                </div>
                <div v-if="activeChores.length === 0" class="text-gray-400 italic">
                    No active chores.
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.print-only {
    display: none;
}

@media print {
    .print-only {
        display: block !important;
        padding: 1cm;
        margin: 0;
        color: black;
    }
}
</style>
