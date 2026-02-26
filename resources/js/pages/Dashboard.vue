<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Calendar,
    CheckCircle2,
    ClipboardList,
    CookingPot,
    Flame,
    ListChecks,
    Medal,
    Trophy,
    UtensilsCrossed,
} from 'lucide-vue-next';
import { ref, onMounted } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/AppLayout.vue';
import { EVENT_COLORS, getEventColor, formatEventTime } from '@/lib/calendar';
import { type BreadcrumbItem } from '@/types';
import type { UpcomingBill } from '@/types/budgeting';
import type { CalendarEvent } from '@/types/calendar';
import type { ChoreAssignment, MemberScore } from '@/types/chores';
import type { FamilyList, FamilyListItem } from '@/types/lists';
import type { MealType } from '@/types/meal-plans';
import { MEAL_TYPE_LABELS } from '@/types/meal-plans';

type DashboardMeal = {
    id: number;
    meal_type: MealType;
    name: string;
    recipe_id: number | null;
};

const props = defineProps<{
    todaysBills: UpcomingBill[];
    todaysChores: ChoreAssignment[];
    todaysMeals: DashboardMeal[];
    pinnedLists: FamilyList[];
    scoreboardSummary: MemberScore[];
    can: {
        viewBills: boolean;
        viewEvents: boolean;
        viewMeals: boolean;
        viewChores: boolean;
        viewLists: boolean;
    };
}>();

const medalColors = ['text-yellow-500', 'text-gray-400', 'text-amber-700'];

const mealTypeColors: Record<MealType, string> = {
    breakfast: 'bg-amber-500',
    lunch: 'bg-green-500',
    dinner: 'bg-blue-500',
    snack: 'bg-purple-500',
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/',
    },
];

// Events state
const events = ref<CalendarEvent[]>([]);
const eventsLoading = ref(false);
const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;

function formatAmount(amount: number): string {
    return new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD' }).format(amount);
}

async function loadEvents() {
    eventsLoading.value = true;
    try {
        const start = new Date();
        start.setHours(0, 0, 0, 0);
        const end = new Date(start);
        end.setHours(23, 59, 59, 999);

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
            const allEvents: CalendarEvent[] = await response.json();
            events.value = allEvents.filter(e => e.source !== 'bill');
        }
    } finally {
        eventsLoading.value = false;
    }
}

onMounted(() => {
    if (props.can.viewEvents) {
        loadEvents();
    }
});

const MAX_PINNED_ITEMS = 8;

function xsrfHeaders(): HeadersInit {
    return {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': decodeURIComponent(
            document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
        ),
    };
}

async function toggleListItem(item: FamilyListItem) {
    await fetch(`/list-items/${item.id}/toggle`, {
        method: 'PATCH',
        headers: xsrfHeaders(),
    });
    item.is_completed = !item.is_completed;
}

function pinnedListProgress(list: FamilyList): string {
    const items = list.items ?? [];
    const done = items.filter(i => i.is_completed).length;
    return `${done}/${items.length}`;
}
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="grid gap-4 md:grid-cols-2">
                <!-- Today's Bills -->
                <div v-if="can.viewBills" class="rounded-lg border p-4 space-y-3">
                    <h3 class="text-sm font-semibold">Today's Bills</h3>
                    <div v-if="todaysBills.length === 0" class="rounded-lg border border-dashed p-8 text-center">
                        <CheckCircle2 class="mx-auto mb-2 h-8 w-8 text-muted-foreground/30" />
                        <p class="text-sm text-muted-foreground">No bills due today.</p>
                        <p class="mt-1 text-xs text-muted-foreground/70">Bills scheduled for today will appear here.</p>
                    </div>
                    <ul v-else class="space-y-2">
                        <li
                            v-for="bill in todaysBills"
                            :key="bill.id"
                            class="flex items-center gap-3"
                        >
                            <span
                                class="size-2.5 shrink-0 rounded-full"
                                :class="EVENT_COLORS[bill.category.color]?.dot ?? 'bg-gray-400'"
                            />
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-medium truncate">{{ bill.name }}</div>
                                <div class="text-xs text-muted-foreground">{{ bill.category?.name }}</div>
                            </div>
                            <div class="text-sm font-medium tabular-nums">
                                {{ formatAmount(bill.amount) }}
                            </div>
                            <Badge v-if="bill.is_paid_this_month" variant="secondary">Paid</Badge>
                        </li>
                    </ul>
                </div>

                <!-- Today's Events -->
                <div v-if="can.viewEvents" class="rounded-lg border p-4 space-y-3">
                    <h3 class="text-sm font-semibold">Today's Events</h3>
                    <div v-if="eventsLoading" class="flex items-center justify-center py-6">
                        <Spinner class="size-5" />
                    </div>
                    <div v-else-if="events.length === 0" class="rounded-lg border border-dashed p-8 text-center">
                        <Calendar class="mx-auto mb-2 h-8 w-8 text-muted-foreground/30" />
                        <p class="text-sm text-muted-foreground">No events today.</p>
                        <p class="mt-1 text-xs text-muted-foreground/70">Scheduled events will show up here.</p>
                    </div>
                    <ul v-else class="space-y-2">
                        <li
                            v-for="event in events"
                            :key="event.id"
                            class="flex items-center gap-3"
                        >
                            <span
                                class="size-2.5 shrink-0 rounded-full"
                                :class="EVENT_COLORS[getEventColor(event)].dot"
                            />
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-medium truncate">{{ event.title }}</div>
                                <div class="text-xs text-muted-foreground">
                                    {{ event.is_all_day ? 'All day' : formatEventTime(event.starts_at, timezone) }}
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>

                <!-- Today's Meals -->
                <div v-if="can.viewMeals" class="rounded-lg border p-4 space-y-3">
                    <h3 class="text-sm font-semibold">Today's Meals</h3>
                    <div v-if="todaysMeals.length === 0" class="rounded-lg border border-dashed p-8 text-center">
                        <UtensilsCrossed class="mx-auto mb-2 h-8 w-8 text-muted-foreground/30" />
                        <p class="text-sm text-muted-foreground">No meals planned for today.</p>
                        <p class="mt-1 text-xs text-muted-foreground/70">Add meals from the meal plan page.</p>
                    </div>
                    <ul v-else class="space-y-2">
                        <li
                            v-for="meal in todaysMeals"
                            :key="meal.id"
                            class="flex items-center gap-3"
                        >
                            <span
                                class="size-2.5 shrink-0 rounded-full"
                                :class="mealTypeColors[meal.meal_type]"
                            />
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-medium truncate">{{ meal.name }}</div>
                                <div class="text-xs text-muted-foreground">
                                    {{ MEAL_TYPE_LABELS[meal.meal_type] }}
                                </div>
                            </div>
                            <CookingPot v-if="meal.recipe_id" class="h-3.5 w-3.5 shrink-0 text-muted-foreground" />
                        </li>
                    </ul>
                </div>

                <!-- Today's Chores -->
                <div v-if="can.viewChores" class="rounded-lg border p-4 space-y-3">
                    <h3 class="text-sm font-semibold">Today's Chores</h3>
                    <div v-if="todaysChores.length === 0" class="rounded-lg border border-dashed p-8 text-center">
                        <ClipboardList class="mx-auto mb-2 h-8 w-8 text-muted-foreground/30" />
                        <p class="text-sm text-muted-foreground">No chores scheduled for today.</p>
                        <p class="mt-1 text-xs text-muted-foreground/70">Assigned chores will appear here.</p>
                    </div>
                    <ul v-else class="space-y-2">
                        <li
                            v-for="assignment in todaysChores"
                            :key="assignment.id"
                            class="flex items-center gap-3"
                        >
                            <span
                                class="size-2.5 shrink-0 rounded-full"
                                :class="EVENT_COLORS[assignment.family_member.color]?.dot ?? 'bg-gray-400'"
                            />
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-medium truncate">{{ assignment.chore?.name }}</div>
                                <div class="text-xs text-muted-foreground">
                                    {{ assignment.family_member.nickname || assignment.family_member.name }}
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>

                <!-- Scoreboard Summary -->
                <div v-if="can.viewChores && scoreboardSummary.length > 0" class="rounded-lg border p-4 space-y-3">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold flex items-center gap-2">
                            <Trophy class="h-4 w-4" />
                            This Week's Top
                        </h3>
                        <Link href="/scoreboard" class="text-xs text-primary hover:underline">
                            View all &rarr;
                        </Link>
                    </div>
                    <div class="space-y-2">
                        <div
                            v-for="(score, i) in scoreboardSummary"
                            :key="score.family_member_id"
                            class="flex items-center gap-2 rounded-lg border px-2.5 py-2 transition-colors sm:gap-3 sm:px-3"
                            :class="[
                                i === 0 ? 'bg-yellow-50 dark:bg-yellow-900/20 border-yellow-200 dark:border-yellow-800' :
                                i === 1 ? 'bg-gray-50 dark:bg-gray-800/30 border-gray-200 dark:border-gray-700' :
                                i === 2 ? 'bg-amber-50 dark:bg-amber-900/20 border-amber-200 dark:border-amber-800' :
                                'border-transparent'
                            ]"
                        >
                            <Medal class="h-4 w-4 shrink-0" :class="medalColors[i]" />
                            <span
                                class="h-2.5 w-2.5 shrink-0 rounded-full"
                                :class="EVENT_COLORS[score.color as keyof typeof EVENT_COLORS]?.dot ?? 'bg-gray-400'"
                            />
                            <div class="flex-1 min-w-0">
                                <span class="text-sm font-medium truncate block">{{ score.name }}</span>
                                <span class="hidden text-xs text-muted-foreground sm:inline">
                                    {{ score.chore_points }} chores<template v-if="score.bonus_points"> + {{ score.bonus_points }} bonus</template>
                                </span>
                            </div>
                            <span class="text-sm font-semibold tabular-nums shrink-0">{{ score.weekly_total }} pts</span>
                            <span
                                v-if="score.streak >= 3"
                                class="flex items-center gap-0.5 shrink-0 rounded-full px-1.5 py-0.5 text-xs font-medium"
                                :class="score.streak >= 7 ? 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300'"
                            >
                                <Flame class="h-3 w-3" />
                                {{ score.streak }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Pinned Lists -->
                <template v-if="can.viewLists">
                <div v-for="list in pinnedLists" :key="`list-${list.id}`" class="rounded-lg border p-4 space-y-3">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold">
                            <a :href="`/lists/${list.id}`" class="hover:underline">{{ list.name }}</a>
                        </h3>
                        <span class="text-xs text-muted-foreground">{{ pinnedListProgress(list) }} done</span>
                    </div>
                    <div v-if="!list.items || list.items.length === 0" class="rounded-lg border border-dashed p-8 text-center">
                        <ListChecks class="mx-auto mb-2 h-8 w-8 text-muted-foreground/30" />
                        <p class="text-sm text-muted-foreground">No items in this list.</p>
                        <p class="mt-1 text-xs text-muted-foreground/70">Add items from the list page.</p>
                    </div>
                    <ul v-else class="space-y-2">
                        <li
                            v-for="item in list.items!.slice(0, MAX_PINNED_ITEMS)"
                            :key="item.id"
                            class="flex items-center gap-2 cursor-pointer"
                            @click="toggleListItem(item)"
                        >
                            <Checkbox
                                :model-value="item.is_completed"
                                @click.stop
                                @update:model-value="toggleListItem(item)"
                            />
                            <span
                                class="text-sm truncate"
                                :style="item.is_completed ? { textDecoration: 'line-through', color: 'var(--color-muted-foreground)' } : {}"
                            >
                                {{ item.name }}<span v-if="item.quantity" class="text-muted-foreground"> ({{ item.quantity }})</span>
                            </span>
                        </li>
                        <li v-if="list.items!.length > MAX_PINNED_ITEMS" class="pt-1">
                            <a :href="`/lists/${list.id}`" class="text-xs text-primary hover:underline">
                                View all {{ list.items!.length }} items &rarr;
                            </a>
                        </li>
                    </ul>
                </div>
                </template>
            </div>
        </div>
    </AppLayout>
</template>
