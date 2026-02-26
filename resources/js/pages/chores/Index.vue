<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { CalendarDays, Check, CheckCircle2, ChevronLeft, ChevronRight, ClipboardList, Grid3X3, List, Pencil, Plus, Printer, Star, Trash2 } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import CellPicker from '@/components/chores/CellPicker.vue';
import ChoreModal from '@/components/chores/ChoreModal.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
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
import type { Chore, ChoreCompletion, DayOfWeek } from '@/types/chores';
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
    todaysCompletions: ChoreCompletion[];
    can: {
        create: boolean;
        edit: boolean;
        delete: boolean;
        assign: boolean;
    };
}>();

// Today's completion tracking
const todayStr = new Date().toISOString().slice(0, 10);
const todayDow = new Date().getDay() as DayOfWeek;
const completionIds = ref(new Set(props.todaysCompletions.map(c => c.chore_assignment_id)));
const isTogglingCompletion = ref(false);

type MemberProgress = {
    member: FamilyMember;
    assignments: { assignment: { id: number; chore: Chore }; completed: boolean }[];
    earned: number;
    possible: number;
};

const todaysProgress = computed<MemberProgress[]>(() => {
    return props.familyMembers.map(member => {
        const memberAssignments = props.chores
            .filter(c => c.is_active)
            .flatMap(chore =>
                chore.assignments
                    .filter(a => a.day_of_week === todayDow && a.family_member_id === member.id)
                    .map(a => ({
                        assignment: { id: a.id, chore },
                        completed: completionIds.value.has(a.id),
                    })),
            );

        const earned = memberAssignments
            .filter(a => a.completed)
            .reduce((sum, a) => sum + a.assignment.chore.points, 0);
        const possible = memberAssignments.reduce((sum, a) => sum + a.assignment.chore.points, 0);

        return { member, assignments: memberAssignments, earned, possible };
    }).filter(mp => mp.assignments.length > 0);
});

function xsrfHeaders(): HeadersInit {
    return {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': decodeURIComponent(
            document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
        ),
    };
}

async function toggleCompletion(assignmentId: number) {
    isTogglingCompletion.value = true;
    try {
        const response = await fetch('/chore-completions/toggle', {
            method: 'POST',
            headers: xsrfHeaders(),
            body: JSON.stringify({
                chore_assignment_id: assignmentId,
                date: todayStr,
            }),
        });
        if (response.ok) {
            const data = await response.json();
            if (data.completed) {
                completionIds.value.add(assignmentId);
            } else {
                completionIds.value.delete(assignmentId);
            }
            // Force reactivity
            completionIds.value = new Set(completionIds.value);
        }
    } finally {
        isTogglingCompletion.value = false;
    }
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Chores' },
];

const days: DayOfWeek[] = [0, 1, 2, 3, 4, 5, 6];
const view = ref<ChoreView>('grid');

function toggleBtnClass(active: boolean): string {
    return `items-center gap-1 px-3 py-1.5 text-sm font-medium transition-colors ${
        active
            ? 'bg-primary text-primary-foreground'
            : 'hover:bg-muted text-muted-foreground'
    }`;
}

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

// Mobile view toggle
const mobileView = ref<'day' | 'all'>('day');

// Assign management dialog (mobile all-chores view)
const showAssignDialog = ref(false);
const assignDialogChoreId = ref<number | null>(null);
const assignDialogDay = ref<DayOfWeek>(todayDayOfWeek);

const assignDialogChore = computed(() =>
    assignDialogChoreId.value
        ? props.chores.find(c => c.id === assignDialogChoreId.value) ?? null
        : null,
);

const assignDialogAssignedIds = computed(() => {
    if (!assignDialogChore.value) return new Set<number>();
    return new Set(
        assignDialogChore.value.assignments
            .filter(a => a.day_of_week === assignDialogDay.value)
            .map(a => a.family_member_id),
    );
});

function openAssignDialog(chore: Chore) {
    assignDialogChoreId.value = chore.id;
    assignDialogDay.value = todayDayOfWeek;
    showAssignDialog.value = true;
}

async function toggleAssignmentFromManage(choreId: number, memberId: number, day: DayOfWeek) {
    isTogglingAssignment.value = true;
    await toggleAssignment(choreId, memberId, day);
    isTogglingAssignment.value = false;
}

function editFromAssignDialog() {
    if (!assignDialogChore.value) return;
    showAssignDialog.value = false;
    openEditChore(assignDialogChore.value);
}

function deleteFromAssignDialog() {
    if (!assignDialogChore.value) return;
    showAssignDialog.value = false;
    openDeleteChore(assignDialogChore.value);
}

function getUniqueMembers(chore: Chore): FamilyMember[] {
    const seen = new Set<number>();
    const members: FamilyMember[] = [];
    for (const a of chore.assignments) {
        if (!seen.has(a.family_member_id)) {
            seen.add(a.family_member_id);
            members.push(a.family_member);
        }
    }
    return members;
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4" data-print-hide>
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <h2 class="text-lg font-semibold">Chore Chart</h2>
                <div class="flex items-center gap-2">
                    <div v-if="chores.length > 0" class="flex rounded-lg border border-border overflow-hidden">
                        <button :class="['hidden md:flex', toggleBtnClass(view === 'grid')]" @click="view = 'grid'">
                            <Grid3X3 class="h-3.5 w-3.5" /> Grid
                        </button>
                        <button :class="['hidden md:flex', toggleBtnClass(view === 'day')]" @click="view = 'day'">
                            <CalendarDays class="h-3.5 w-3.5" /> By Day
                        </button>
                        <button :class="['flex md:hidden', toggleBtnClass(mobileView === 'day')]" @click="mobileView = 'day'">
                            <CalendarDays class="h-3.5 w-3.5" /> Day
                        </button>
                        <button :class="['flex md:hidden', toggleBtnClass(mobileView === 'all')]" @click="mobileView = 'all'">
                            <List class="h-3.5 w-3.5" /> All
                        </button>
                    </div>
                    <Button v-if="chores.length > 0" size="sm" variant="outline" class="hidden md:inline-flex" @click="printChart">
                        <Printer class="h-4 w-4 mr-1" /> Print
                    </Button>
                    <Button v-if="can.create" size="sm" @click="openCreateChore">
                        <Plus class="h-4 w-4 sm:mr-1" />
                        <span class="hidden sm:inline">Add Chore</span>
                    </Button>
                </div>
            </div>

            <!-- Today's Progress -->
            <Card v-if="todaysProgress.length > 0">
                <CardHeader class="pb-3">
                    <CardTitle class="text-base flex items-center gap-2">
                        <CheckCircle2 class="h-4 w-4" />
                        Today's Progress
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="space-y-4">
                        <div v-for="mp in todaysProgress" :key="mp.member.id">
                            <div class="flex items-center gap-2 mb-1.5">
                                <span
                                    class="h-2.5 w-2.5 shrink-0 rounded-full"
                                    :class="EVENT_COLORS[mp.member.color]?.dot ?? 'bg-gray-400'"
                                />
                                <span class="text-sm font-medium">{{ mp.member.nickname || mp.member.name }}</span>
                                <span class="text-xs font-medium ml-auto tabular-nums">
                                    <Star class="h-3 w-3 inline -mt-0.5" /> {{ mp.earned }}/{{ mp.possible }} pts
                                </span>
                            </div>
                            <!-- Progress bar -->
                            <div class="h-2 w-full rounded-full bg-muted overflow-hidden mb-2">
                                <div
                                    class="h-full rounded-full transition-all duration-500"
                                    :class="mp.earned === mp.possible && mp.possible > 0 ? 'bg-emerald-500' : EVENT_COLORS[mp.member.color]?.dot?.replace('bg-', 'bg-') ?? 'bg-blue-500'"
                                    :style="{ width: mp.possible > 0 ? (mp.earned / mp.possible * 100) + '%' : '0%' }"
                                />
                            </div>
                            <div class="space-y-1 pl-5">
                                <button
                                    v-for="item in mp.assignments"
                                    :key="item.assignment.id"
                                    class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-sm transition-colors hover:bg-accent"
                                    :disabled="isTogglingCompletion"
                                    @click="toggleCompletion(item.assignment.id)"
                                >
                                    <Checkbox
                                        :model-value="item.completed"
                                        @click.stop
                                        @update:model-value="toggleCompletion(item.assignment.id)"
                                    />
                                    <span class="flex-1 text-left" :class="item.completed ? 'line-through text-muted-foreground' : 'font-medium'">
                                        {{ item.assignment.chore.name }}
                                    </span>
                                    <Badge :variant="item.completed ? 'outline' : 'secondary'" class="text-xs">{{ item.assignment.chore.points }} pts</Badge>
                                </button>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Empty state -->
            <div v-if="chores.length === 0" class="rounded-lg border border-dashed p-8 text-center">
                <ClipboardList class="mx-auto mb-2 h-8 w-8 text-muted-foreground/30" />
                <p class="text-sm text-muted-foreground">No chores yet.</p>
                <p class="mt-1 text-xs text-muted-foreground/70">Click "Add Chore" to get started.</p>
            </div>

            <template v-else>
                <!-- ===== MOBILE DAY VIEW ===== -->
                <div v-if="mobileView === 'day'" class="md:hidden space-y-3" @touchstart="swipeTouchStart" @touchend="swipeTouchEnd">
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
                    <div v-if="choresByDay[selectedDayIndex].length === 0" class="rounded-lg border border-dashed p-8 text-center">
                        <CalendarDays class="mx-auto mb-2 h-8 w-8 text-muted-foreground/30" />
                        <p class="text-sm text-muted-foreground">No chores on {{ DAY_LABELS_FULL[selectedDayIndex] }}.</p>
                        <p class="mt-1 text-xs text-muted-foreground/70">Assign chores from the grid view.</p>
                    </div>
                    <div v-else class="space-y-2">
                        <button
                            v-for="entry in choresByDay[selectedDayIndex]"
                            :key="entry.chore.id"
                            class="w-full text-left rounded-lg border px-4 py-3 transition-colors hover:bg-accent cursor-pointer"
                            @click="openDetail(entry, selectedDayIndex)"
                        >
                            <div class="font-medium flex items-center gap-1.5">
                                {{ entry.chore.name }}
                                <Badge variant="secondary" class="text-xs">{{ entry.chore.points }} pts</Badge>
                            </div>
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

                <!-- ===== MOBILE ALL CHORES VIEW ===== -->
                <div v-if="mobileView === 'all'" class="md:hidden space-y-2">
                    <div v-if="activeChores.length === 0" class="rounded-lg border border-dashed p-8 text-center">
                        <ClipboardList class="mx-auto mb-2 h-8 w-8 text-muted-foreground/30" />
                        <p class="text-sm text-muted-foreground">No active chores.</p>
                        <p class="mt-1 text-xs text-muted-foreground/70">Add a chore to start assigning tasks.</p>
                    </div>
                    <button
                        v-for="chore in activeChores"
                        :key="chore.id"
                        class="w-full text-left rounded-lg border px-4 py-3 transition-colors hover:bg-accent cursor-pointer"
                        @click="openAssignDialog(chore)"
                    >
                        <div class="font-medium flex items-center gap-1.5">
                            {{ chore.name }}
                            <Badge variant="secondary" class="text-xs">{{ chore.points }} pts</Badge>
                        </div>
                        <div v-if="chore.description" class="text-xs text-muted-foreground mt-0.5">{{ chore.description }}</div>
                        <div v-if="chore.assignments.length > 0" class="mt-2 flex flex-wrap gap-2">
                            <span
                                v-for="member in getUniqueMembers(chore)"
                                :key="member.id"
                                class="flex items-center gap-1.5 text-xs text-muted-foreground"
                            >
                                <span
                                    class="h-2.5 w-2.5 shrink-0 rounded-full"
                                    :class="EVENT_COLORS[member.color]?.dot ?? 'bg-blue-500'"
                                />
                                {{ member.nickname || member.name }}
                            </span>
                        </div>
                        <div v-else class="mt-1 text-xs text-muted-foreground italic">
                            No assignments yet
                        </div>
                    </button>
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
                                            <div class="font-medium truncate flex items-center gap-1.5">
                                                {{ chore.name }}
                                                <Badge variant="secondary" class="text-xs shrink-0">{{ chore.points }} pts</Badge>
                                            </div>
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
                                <div class="font-medium text-sm flex items-center gap-1.5">
                                    {{ entry.chore.name }}
                                    <Badge v-if="entry.chore.points" variant="secondary" class="text-xs">{{ entry.chore.points }} pts</Badge>
                                </div>
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

            <!-- Assign management dialog (mobile all-chores view) -->
            <Dialog :open="showAssignDialog" @update:open="showAssignDialog = $event">
                <DialogContent class="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>{{ assignDialogChore?.name }}</DialogTitle>
                        <DialogDescription v-if="assignDialogChore?.description">
                            {{ assignDialogChore.description }}
                        </DialogDescription>
                    </DialogHeader>
                    <div v-if="assignDialogChore" class="space-y-4 pt-2">
                        <!-- Day picker -->
                        <div class="flex gap-0.5">
                            <button
                                v-for="day in days"
                                :key="day"
                                class="flex-1 py-1.5 rounded-md text-xs font-medium transition-colors text-center"
                                :class="day === assignDialogDay ? 'bg-primary text-primary-foreground' : 'hover:bg-muted text-muted-foreground'"
                                @click="assignDialogDay = day"
                            >
                                {{ DAY_LABELS[day] }}
                            </button>
                        </div>

                        <div class="text-sm text-muted-foreground font-medium">
                            {{ DAY_LABELS_FULL[assignDialogDay] }}
                        </div>

                        <!-- Member toggles -->
                        <div v-if="can.assign" class="space-y-1">
                            <button
                                v-for="member in familyMembers"
                                :key="member.id"
                                class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-sm transition-colors hover:bg-accent"
                                :disabled="isTogglingAssignment"
                                @click="toggleAssignmentFromManage(assignDialogChore!.id, member.id, assignDialogDay)"
                            >
                                <span
                                    class="h-3 w-3 shrink-0 rounded-full"
                                    :class="EVENT_COLORS[member.color]?.dot ?? 'bg-blue-500'"
                                />
                                <span class="flex-1 text-left truncate">{{ member.nickname || member.name }}</span>
                                <Check v-if="assignDialogAssignedIds.has(member.id)" class="h-3.5 w-3.5 shrink-0 text-emerald-500" />
                            </button>
                        </div>

                        <!-- Read-only if no assign permission -->
                        <div v-else class="space-y-1.5">
                            <div
                                v-for="member in familyMembers"
                                :key="member.id"
                                class="flex items-center gap-2 text-sm"
                                :class="{ 'text-muted-foreground': !assignDialogAssignedIds.has(member.id) }"
                            >
                                <span
                                    class="h-3 w-3 shrink-0 rounded-full"
                                    :class="EVENT_COLORS[member.color]?.dot ?? 'bg-blue-500'"
                                />
                                {{ member.nickname || member.name }}
                                <Check v-if="assignDialogAssignedIds.has(member.id)" class="h-3.5 w-3.5 shrink-0 text-emerald-500" />
                            </div>
                        </div>
                    </div>
                    <DialogFooter v-if="can.edit || can.delete" class="gap-2">
                        <Button v-if="can.edit" variant="outline" size="sm" @click="editFromAssignDialog">
                            <Pencil class="h-3.5 w-3.5 mr-1" />
                            Edit
                        </Button>
                        <Button v-if="can.delete" variant="destructive" size="sm" @click="deleteFromAssignDialog">
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
                                <div class="font-semibold">{{ entry.chore.name }}<span v-if="entry.chore.points"> ({{ entry.chore.points }} pts)</span></div>
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
        padding: 0.5cm;
        margin: 0;
        color: black;
    }
}
</style>
