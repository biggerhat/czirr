<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import MealPlanEntryModal from '@/components/meal-plans/MealPlanEntryModal.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { ChevronLeft, ChevronRight, Clock, CookingPot, ExternalLink, Pencil, Plus, Printer, ShoppingCart, Timer, Trash2, Users } from 'lucide-vue-next';
import { useSwipe } from '@/composables/useSwipe';
import type { BreadcrumbItem } from '@/types';
import type { CustomMeal, MealPlanEntry, MealType } from '@/types/meal-plans';
import { MEAL_TYPE_LABELS, MEAL_TYPES } from '@/types/meal-plans';
import type { Recipe } from '@/types/recipes';
import { DIFFICULTY_LABELS } from '@/types/recipes';

const props = defineProps<{
    entries: MealPlanEntry[];
    recipes: Pick<Recipe, 'id' | 'name'>[];
    customMeals: CustomMeal[];
    startDate: string;
    endDate: string;
    can: {
        create: boolean;
        edit: boolean;
        delete: boolean;
        generateGroceryList: boolean;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Meal Plans' },
];

// Build the 14 days from startDate
const days = computed(() => {
    const result: string[] = [];
    const start = new Date(props.startDate + 'T00:00:00');
    for (let i = 0; i < 14; i++) {
        const d = new Date(start);
        d.setDate(start.getDate() + i);
        result.push(d.toISOString().split('T')[0]);
    }
    return result;
});

const week1 = computed(() => days.value.slice(0, 7));
const week2 = computed(() => days.value.slice(7, 14));

const today = new Date().toISOString().split('T')[0];

// Index entries by date+meal_type for fast lookup
const entryMap = computed(() => {
    const map = new Map<string, MealPlanEntry[]>();
    for (const entry of props.entries) {
        const key = `${entry.date}|${entry.meal_type}`;
        if (!map.has(key)) map.set(key, []);
        map.get(key)!.push(entry);
    }
    return map;
});

function getEntries(date: string, mealType: MealType): MealPlanEntry[] {
    return entryMap.value.get(`${date}|${mealType}`) ?? [];
}

// Navigation
function navigate(direction: 'prev' | 'next' | 'today') {
    let start: string;
    if (direction === 'today') {
        // Current week's Monday
        const now = new Date();
        const day = now.getDay();
        const diff = day === 0 ? -6 : 1 - day;
        const monday = new Date(now);
        monday.setDate(now.getDate() + diff);
        start = monday.toISOString().split('T')[0];
    } else {
        const current = new Date(props.startDate + 'T00:00:00');
        current.setDate(current.getDate() + (direction === 'next' ? 14 : -14));
        start = current.toISOString().split('T')[0];
    }
    router.get('/meal-plans', { start }, { preserveState: true });
}

// Date formatting helpers
function formatDayHeader(dateStr: string): string {
    const d = new Date(dateStr + 'T00:00:00');
    return d.toLocaleDateString('en-US', { weekday: 'short' });
}

function formatDayNumber(dateStr: string): string {
    const d = new Date(dateStr + 'T00:00:00');
    return d.getDate().toString();
}

function formatMonthLabel(dateStr: string): string {
    const d = new Date(dateStr + 'T00:00:00');
    return d.toLocaleDateString('en-US', { month: 'short' });
}

function formatPeriodLabel(): string {
    const s = new Date(props.startDate + 'T00:00:00');
    const e = new Date(props.endDate + 'T00:00:00');
    const sMonth = s.toLocaleDateString('en-US', { month: 'short' });
    const eMonth = e.toLocaleDateString('en-US', { month: 'short' });
    const sYear = s.getFullYear();
    const eYear = e.getFullYear();

    if (sMonth === eMonth && sYear === eYear) {
        return `${sMonth} ${s.getDate()} – ${e.getDate()}, ${sYear}`;
    }
    if (sYear === eYear) {
        return `${sMonth} ${s.getDate()} – ${eMonth} ${e.getDate()}, ${sYear}`;
    }
    return `${sMonth} ${s.getDate()}, ${sYear} – ${eMonth} ${e.getDate()}, ${eYear}`;
}

// Modal state
const showEntryModal = ref(false);
const editingEntry = ref<MealPlanEntry | null>(null);
const prefillDate = ref<string>('');
const prefillMealType = ref<MealType>('dinner');

function openAddEntry(date: string, mealType: MealType) {
    editingEntry.value = null;
    prefillDate.value = date;
    prefillMealType.value = mealType;
    showEntryModal.value = true;
}

function openEditEntry(entry: MealPlanEntry) {
    editingEntry.value = entry;
    showEntryModal.value = true;
}

function onSaved() {
    router.reload();
}

// Delete
const showDeleteDialog = ref(false);
const deletingEntry = ref<MealPlanEntry | null>(null);
const isDeleting = ref(false);

function openDelete(entry: MealPlanEntry) {
    deletingEntry.value = entry;
    showDeleteDialog.value = true;
}

async function confirmDelete() {
    if (!deletingEntry.value) return;
    isDeleting.value = true;

    await fetch(`/meal-plan-entries/${deletingEntry.value.id}`, {
        method: 'DELETE',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': decodeURIComponent(
                document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
            ),
        },
    });
    isDeleting.value = false;
    showDeleteDialog.value = false;
    router.reload();
}

// Detail dialog
const showDetailDialog = ref(false);
const detailEntry = ref<MealPlanEntry | null>(null);

function openDetail(entry: MealPlanEntry) {
    detailEntry.value = entry;
    showDetailDialog.value = true;
}

function formatDetailDate(dateStr: string): string {
    const d = new Date(dateStr + 'T00:00:00');
    return d.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' });
}

// Print
function printPlan() {
    window.print();
}

function formatPrintDate(dateStr: string): string {
    const d = new Date(dateStr + 'T00:00:00');
    return d.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
}

// Grocery list generation
const isGeneratingList = ref(false);
const generateError = ref('');
const showGroceryConflict = ref(false);
const conflictExistingId = ref<number | null>(null);

function groceryHeaders() {
    return {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': decodeURIComponent(
            document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
        ),
    };
}

async function generateGroceryList(mode: 'create' | 'replace' | 'new' = 'create') {
    isGeneratingList.value = true;
    generateError.value = '';
    showGroceryConflict.value = false;

    try {
        const response = await fetch('/meal-plans/generate-grocery-list', {
            method: 'POST',
            headers: groceryHeaders(),
            body: JSON.stringify({ start: props.startDate, end: props.endDate, mode }),
        });

        const data = await response.json();

        if (response.status === 409 && data.conflict) {
            conflictExistingId.value = data.existing_id;
            showGroceryConflict.value = true;
            return;
        }

        if (!response.ok) {
            generateError.value = data.message || 'Failed to generate grocery list.';
            return;
        }

        router.visit(`/lists/${data.id}`);
    } catch {
        generateError.value = 'Failed to generate grocery list.';
    } finally {
        isGeneratingList.value = false;
    }
}

function handleConflictReplace() {
    showGroceryConflict.value = false;
    generateGroceryList('replace');
}

function handleConflictViewExisting() {
    showGroceryConflict.value = false;
    if (conflictExistingId.value) {
        router.visit(`/lists/${conflictExistingId.value}`);
    }
}

// Meal type colors for visual grouping
const mealTypeColors: Record<MealType, string> = {
    breakfast: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
    lunch: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
    dinner: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
    snack: 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300',
};

const mealTypeBorderColors: Record<MealType, string> = {
    breakfast: 'border-amber-300 dark:border-amber-700',
    lunch: 'border-green-300 dark:border-green-700',
    dinner: 'border-blue-300 dark:border-blue-700',
    snack: 'border-purple-300 dark:border-purple-700',
};

// Mobile day view
const selectedDayIndex = ref(0);

const selectedDay = computed(() => days.value[selectedDayIndex.value]);

function prevDay() {
    if (selectedDayIndex.value > 0) selectedDayIndex.value--;
}

function nextDay() {
    if (selectedDayIndex.value < days.value.length - 1) selectedDayIndex.value++;
}

function formatFullDay(dateStr: string): string {
    const d = new Date(dateStr + 'T00:00:00');
    return d.toLocaleDateString('en-US', { weekday: 'long', month: 'short', day: 'numeric' });
}

const DAY_ABBREVS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

const { onTouchStart: swipeTouchStart, onTouchEnd: swipeTouchEnd } = useSwipe(nextDay, prevDay);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4" data-print-hide>
            <!-- Header -->
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-lg font-semibold shrink-0">Meal Plans</h2>
                <div class="flex items-center gap-2">
                    <Button size="sm" variant="outline" @click="navigate('prev')">
                        <ChevronLeft class="h-4 w-4" />
                    </Button>
                    <Button size="sm" variant="outline" @click="navigate('today')">
                        Today
                    </Button>
                    <Button size="sm" variant="outline" @click="navigate('next')">
                        <ChevronRight class="h-4 w-4" />
                    </Button>
                    <Button v-if="entries.length > 0" size="sm" variant="outline" class="hidden sm:inline-flex" @click="printPlan">
                        <Printer class="h-4 w-4 mr-1" />
                        Print
                    </Button>
                    <Button v-if="can.generateGroceryList" size="sm" variant="outline" class="hidden sm:inline-flex" :disabled="isGeneratingList" @click="generateGroceryList()">
                        <ShoppingCart class="h-4 w-4 mr-1" />
                        {{ isGeneratingList ? 'Generating...' : 'Grocery List' }}
                    </Button>
                    <span class="text-sm font-medium ml-2 hidden md:inline">{{ formatPeriodLabel() }}</span>
                </div>
            </div>

            <!-- Mobile action row + period label -->
            <div class="flex items-center justify-between sm:hidden">
                <span class="text-sm font-medium">{{ formatPeriodLabel() }}</span>
                <div class="flex items-center gap-2">
                    <Button v-if="entries.length > 0" size="sm" variant="outline" @click="printPlan">
                        <Printer class="h-4 w-4" />
                    </Button>
                    <Button v-if="can.generateGroceryList" size="sm" variant="outline" :disabled="isGeneratingList" @click="generateGroceryList()">
                        <ShoppingCart class="h-4 w-4 mr-1" />
                        {{ isGeneratingList ? 'Generating...' : 'Grocery List' }}
                    </Button>
                </div>
            </div>

            <!-- Period label (tablet - between sm and md) -->
            <div class="text-sm font-medium text-center hidden sm:block md:hidden">{{ formatPeriodLabel() }}</div>

            <!-- Grocery list error -->
            <div v-if="generateError" class="flex items-center justify-between rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                <span>{{ generateError }}</span>
                <button class="ml-2 font-medium hover:underline" @click="generateError = ''">Dismiss</button>
            </div>

            <!-- ===== MOBILE DAY VIEW ===== -->
            <div class="md:hidden space-y-3" @touchstart="swipeTouchStart" @touchend="swipeTouchEnd">
                <!-- Day strip -->
                <div class="flex items-center gap-1">
                    <button
                        class="p-1 rounded hover:bg-accent shrink-0"
                        :disabled="selectedDayIndex === 0"
                        :class="{ 'opacity-30': selectedDayIndex === 0 }"
                        @click="prevDay"
                    >
                        <ChevronLeft class="h-4 w-4" />
                    </button>
                    <div class="flex-1 flex gap-0.5 overflow-x-auto">
                        <button
                            v-for="(date, i) in days"
                            :key="date"
                            class="flex flex-col items-center px-2 py-1 rounded-md text-xs min-w-[2.5rem] transition-colors"
                            :class="[
                                i === selectedDayIndex ? 'bg-primary text-primary-foreground' : date === today ? 'bg-primary/10' : 'hover:bg-muted',
                            ]"
                            @click="selectedDayIndex = i"
                        >
                            <span class="font-medium">{{ DAY_ABBREVS[i % 7] }}</span>
                            <span class="text-[10px]" :class="i === selectedDayIndex ? 'text-primary-foreground/80' : 'text-muted-foreground'">{{ formatDayNumber(date) }}</span>
                        </button>
                    </div>
                    <button
                        class="p-1 rounded hover:bg-accent shrink-0"
                        :disabled="selectedDayIndex === days.length - 1"
                        :class="{ 'opacity-30': selectedDayIndex === days.length - 1 }"
                        @click="nextDay"
                    >
                        <ChevronRight class="h-4 w-4" />
                    </button>
                </div>

                <!-- Current day header -->
                <div class="text-center">
                    <div class="text-sm font-semibold">{{ formatFullDay(selectedDay) }}</div>
                    <div class="text-xs text-muted-foreground">Week {{ selectedDayIndex < 7 ? 1 : 2 }}</div>
                </div>

                <!-- Meal type sections -->
                <div v-for="mt in MEAL_TYPES" :key="mt" class="rounded-lg border overflow-hidden">
                    <div class="px-3 py-2 text-sm font-medium border-b" :class="[mealTypeColors[mt], mealTypeBorderColors[mt]]">
                        {{ MEAL_TYPE_LABELS[mt] }}
                    </div>
                    <div class="p-2 space-y-1">
                        <button
                            v-for="entry in getEntries(selectedDay, mt)"
                            :key="entry.id"
                            type="button"
                            class="w-full flex items-center gap-2 rounded-md px-3 py-2 text-sm text-left hover:bg-accent transition-colors"
                            @click="openDetail(entry)"
                        >
                            <CookingPot v-if="entry.recipe_id" class="h-3.5 w-3.5 shrink-0 opacity-60" />
                            <span class="flex-1 min-w-0 truncate">{{ entry.name }}</span>
                            <span v-if="can.edit || can.delete" class="flex items-center gap-1 shrink-0">
                                <button v-if="can.edit" class="p-1 rounded hover:bg-muted" @click.stop="openEditEntry(entry)">
                                    <Pencil class="h-3.5 w-3.5 text-muted-foreground" />
                                </button>
                                <button v-if="can.delete" class="p-1 rounded hover:bg-muted" @click.stop="openDelete(entry)">
                                    <Trash2 class="h-3.5 w-3.5 text-destructive" />
                                </button>
                            </span>
                        </button>
                        <div v-if="getEntries(selectedDay, mt).length === 0" class="text-xs text-muted-foreground/50 text-center py-2">
                            No {{ MEAL_TYPE_LABELS[mt].toLowerCase() }}
                        </div>
                        <button
                            v-if="can.create"
                            class="w-full flex items-center justify-center gap-1 rounded-md border border-dashed border-border text-muted-foreground hover:bg-accent transition-colors text-sm py-2"
                            @click="openAddEntry(selectedDay, mt)"
                        >
                            <Plus class="h-3.5 w-3.5" />
                            Add
                        </button>
                    </div>
                </div>
            </div>

            <!-- ===== DESKTOP WEEK GRIDS ===== -->
            <div v-for="(week, weekIndex) in [week1, week2]" :key="weekIndex" class="hidden md:block space-y-1">
                <div class="text-xs font-medium text-muted-foreground px-1">
                    Week {{ weekIndex + 1 }}
                </div>

                <!-- Day headers -->
                <div class="grid grid-cols-7 lg:grid-cols-[auto_repeat(7,minmax(0,1fr))] gap-1">
                    <div class="hidden lg:flex min-w-[4.5rem]" />
                    <div
                        v-for="date in week"
                        :key="date"
                        class="text-center rounded-t-md px-1 py-1.5"
                        :class="date === today ? 'bg-primary/10' : 'bg-muted/50'"
                    >
                        <div class="text-xs text-muted-foreground">{{ formatDayHeader(date) }}</div>
                        <div class="text-sm font-semibold" :class="date === today ? 'text-primary' : ''">
                            <span v-if="formatDayNumber(date) === '1'" class="text-xs font-normal text-muted-foreground mr-0.5">{{ formatMonthLabel(date) }}</span>
                            {{ formatDayNumber(date) }}
                        </div>
                    </div>
                </div>

                <!-- Meal rows -->
                <div v-for="mt in MEAL_TYPES" :key="mt">
                    <div class="grid grid-cols-7 lg:grid-cols-[auto_repeat(7,minmax(0,1fr))] gap-1">
                        <!-- Meal type label column -->
                        <div class="hidden lg:flex items-center justify-end pr-1.5 text-xs text-muted-foreground whitespace-nowrap min-w-[4.5rem]">
                            {{ MEAL_TYPE_LABELS[mt] }}
                        </div>

                        <div
                            v-for="date in week"
                            :key="`${date}-${mt}`"
                            class="min-h-[48px] rounded-md border border-border/50 p-1 group flex flex-col"
                            :class="date === today ? 'bg-primary/5' : ''"
                        >
                            <!-- Entries -->
                            <div class="space-y-0.5">
                                <button
                                    v-for="entry in getEntries(date, mt)"
                                    :key="entry.id"
                                    type="button"
                                    class="w-full flex items-start gap-0.5 rounded px-1 py-0.5 text-xs group/entry cursor-pointer text-left hover:opacity-80 transition-opacity"
                                    :class="mealTypeColors[mt]"
                                    @click="openDetail(entry)"
                                >
                                    <CookingPot v-if="entry.recipe_id" class="h-3 w-3 shrink-0 mt-0.5 opacity-60" />
                                    <span class="flex-1 truncate leading-tight" :title="entry.name">{{ entry.name }}</span>
                                    <span v-if="can.edit || can.delete" class="hidden group-hover/entry:flex items-center gap-0.5 shrink-0">
                                        <button v-if="can.edit" class="hover:text-foreground" @click.stop="openEditEntry(entry)">
                                            <Pencil class="h-2.5 w-2.5" />
                                        </button>
                                        <button v-if="can.delete" class="hover:text-destructive" @click.stop="openDelete(entry)">
                                            <Trash2 class="h-2.5 w-2.5" />
                                        </button>
                                    </span>
                                </button>
                            </div>

                            <!-- Add button -->
                            <button
                                v-if="can.create"
                                class="w-full flex-1 mt-0.5 min-h-[20px] rounded border border-dashed border-transparent text-muted-foreground/0 group-hover:border-border group-hover:text-muted-foreground hover:!bg-accent transition-colors text-xs flex items-center justify-center"
                                @click="openAddEntry(date, mt)"
                            >
                                <Plus class="h-3 w-3" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Meal type legend (visible on md screens where the row labels are hidden, not mobile since mobile has its own view) -->
            <div class="hidden md:flex lg:hidden flex-wrap items-center gap-2 text-xs">
                <span class="text-muted-foreground font-medium">Meals:</span>
                <span v-for="mt in MEAL_TYPES" :key="mt" class="flex items-center gap-1">
                    <span class="h-2.5 w-2.5 rounded-sm" :class="mealTypeColors[mt]" />
                    {{ MEAL_TYPE_LABELS[mt] }}
                </span>
            </div>

            <!-- Entry modal -->
            <MealPlanEntryModal
                :entry="editingEntry"
                :open="showEntryModal"
                :date="prefillDate"
                :meal-type="prefillMealType"
                :recipes="recipes"
                :custom-meals="customMeals"
                @update:open="showEntryModal = $event"
                @saved="onSaved"
            />

            <!-- Detail dialog -->
            <Dialog :open="showDetailDialog" @update:open="showDetailDialog = $event">
                <DialogContent class="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>{{ detailEntry?.name }}</DialogTitle>
                        <DialogDescription>
                            {{ MEAL_TYPE_LABELS[detailEntry?.meal_type ?? 'dinner'] }} · {{ detailEntry ? formatDetailDate(detailEntry.date) : '' }}
                        </DialogDescription>
                    </DialogHeader>

                    <div class="space-y-3 pt-1">
                        <!-- Custom meal description -->
                        <p v-if="!detailEntry?.recipe_id && detailEntry?.description" class="text-sm text-muted-foreground whitespace-pre-wrap">
                            {{ detailEntry.description }}
                        </p>
                        <p v-if="!detailEntry?.recipe_id && !detailEntry?.description" class="text-sm text-muted-foreground italic">
                            No description.
                        </p>

                        <!-- Linked recipe info -->
                        <template v-if="detailEntry?.recipe">
                            <div class="flex items-center gap-1.5 text-xs text-muted-foreground">
                                <CookingPot class="h-3.5 w-3.5" />
                                Linked recipe
                            </div>

                            <p v-if="detailEntry.recipe.description" class="text-sm text-muted-foreground">
                                {{ detailEntry.recipe.description }}
                            </p>

                            <div class="flex flex-wrap gap-3 text-sm text-muted-foreground">
                                <span class="flex items-center gap-1">
                                    <Timer class="h-3.5 w-3.5" />
                                    {{ detailEntry.recipe.prep_time }}m prep
                                </span>
                                <span class="flex items-center gap-1">
                                    <Clock class="h-3.5 w-3.5" />
                                    {{ detailEntry.recipe.cook_time }}m cook
                                </span>
                                <span class="flex items-center gap-1">
                                    <Users class="h-3.5 w-3.5" />
                                    {{ detailEntry.recipe.servings }} {{ detailEntry.recipe.servings === 1 ? 'serving' : 'servings' }}
                                </span>
                            </div>

                            <Badge variant="secondary" class="text-xs">
                                {{ DIFFICULTY_LABELS[detailEntry.recipe.difficulty] }}
                            </Badge>

                            <!-- Entry-level notes -->
                            <div v-if="detailEntry.description" class="border-t pt-3">
                                <div class="text-xs font-medium text-muted-foreground mb-1">Notes</div>
                                <p class="text-sm whitespace-pre-wrap">{{ detailEntry.description }}</p>
                            </div>

                            <Link
                                :href="`/recipes/${detailEntry.recipe.id}`"
                                class="inline-flex items-center gap-1 text-sm text-primary hover:underline"
                            >
                                <ExternalLink class="h-3.5 w-3.5" />
                                View full recipe
                            </Link>
                        </template>
                    </div>

                    <DialogFooter v-if="can.edit || can.delete" class="gap-2 pt-2">
                        <Button v-if="can.edit" variant="outline" size="sm" @click="showDetailDialog = false; openEditEntry(detailEntry!)">
                            <Pencil class="h-3.5 w-3.5 mr-1" />
                            Edit
                        </Button>
                        <Button v-if="can.delete" variant="outline" size="sm" class="text-destructive" @click="showDetailDialog = false; openDelete(detailEntry!)">
                            <Trash2 class="h-3.5 w-3.5 mr-1" />
                            Remove
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Delete dialog -->
            <Dialog :open="showDeleteDialog" @update:open="showDeleteDialog = $event">
                <DialogContent class="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Remove meal?</DialogTitle>
                        <DialogDescription>
                            This will remove "{{ deletingEntry?.name }}" from the meal plan.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter class="gap-2">
                        <Button variant="outline" @click="showDeleteDialog = false">Cancel</Button>
                        <Button variant="destructive" :disabled="isDeleting" @click="confirmDelete">
                            {{ isDeleting ? 'Removing...' : 'Remove' }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Grocery list conflict dialog -->
            <Dialog :open="showGroceryConflict" @update:open="showGroceryConflict = $event">
                <DialogContent class="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Grocery list already exists</DialogTitle>
                        <DialogDescription>
                            A grocery list for this period already exists. What would you like to do?
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter class="gap-2">
                        <Button variant="outline" @click="showGroceryConflict = false">Cancel</Button>
                        <Button variant="outline" @click="handleConflictViewExisting">
                            View Existing
                        </Button>
                        <Button @click="handleConflictReplace">
                            Replace Items
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>

        <!-- ===== PRINT-ONLY CONTENT ===== -->
        <div class="print-only">
            <h1 class="text-2xl font-bold text-center mb-1">Meal Plan</h1>
            <p class="text-center text-sm text-gray-500 mb-6">{{ formatPeriodLabel() }}</p>

            <template v-for="(week, weekIndex) in [week1, week2]" :key="weekIndex">
                <h2 class="text-sm font-bold mb-1">Week {{ weekIndex + 1 }}</h2>
                <table class="w-full border-collapse border border-black text-xs mb-6">
                    <thead>
                        <tr>
                            <th class="border border-black px-1 py-1 text-left font-bold bg-gray-100 w-[60px]"></th>
                            <th
                                v-for="date in week"
                                :key="date"
                                class="border border-black px-1.5 py-1 text-center font-bold bg-gray-100"
                            >
                                {{ formatPrintDate(date) }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="mt in MEAL_TYPES" :key="mt">
                            <td class="border border-black px-1.5 py-1 font-semibold bg-gray-50 align-top">
                                {{ MEAL_TYPE_LABELS[mt] }}
                            </td>
                            <td
                                v-for="date in week"
                                :key="`${date}-${mt}`"
                                class="border border-black px-1.5 py-1 align-top"
                            >
                                <div
                                    v-for="entry in getEntries(date, mt)"
                                    :key="entry.id"
                                    class="mb-0.5 last:mb-0"
                                >
                                    {{ entry.name }}
                                </div>
                                <div v-if="getEntries(date, mt).length === 0" class="text-gray-300">
                                    &mdash;
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </template>

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
