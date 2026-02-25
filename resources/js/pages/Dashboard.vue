<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { CookingPot } from 'lucide-vue-next';
import { ref, onMounted } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/AppLayout.vue';
import { EVENT_COLORS, getEventColor, formatEventTime } from '@/lib/calendar';
import { type BreadcrumbItem } from '@/types';
import type { UpcomingBill } from '@/types/budgeting';
import type { CalendarEvent } from '@/types/calendar';
import type { ChoreAssignment } from '@/types/chores';
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
    can: {
        viewBills: boolean;
        viewEvents: boolean;
        viewMeals: boolean;
        viewChores: boolean;
        viewLists: boolean;
    };
}>();

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
                <Card v-if="can.viewBills">
                    <CardHeader>
                        <CardTitle>Today's Bills</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div v-if="todaysBills.length === 0" class="text-sm text-muted-foreground py-4 text-center">
                            No bills due today.
                        </div>
                        <ul v-else class="space-y-3">
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
                    </CardContent>
                </Card>

                <!-- Today's Events -->
                <Card v-if="can.viewEvents">
                    <CardHeader>
                        <CardTitle>Today's Events</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div v-if="eventsLoading" class="flex items-center justify-center py-4">
                            <Spinner class="size-5" />
                        </div>
                        <div v-else-if="events.length === 0" class="text-sm text-muted-foreground py-4 text-center">
                            No events today.
                        </div>
                        <ul v-else class="space-y-3">
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
                    </CardContent>
                </Card>

                <!-- Today's Meals -->
                <Card v-if="can.viewMeals">
                    <CardHeader>
                        <CardTitle>Today's Meals</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div v-if="todaysMeals.length === 0" class="text-sm text-muted-foreground py-4 text-center">
                            No meals planned for today.
                        </div>
                        <ul v-else class="space-y-3">
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
                    </CardContent>
                </Card>

                <!-- Today's Chores -->
                <Card v-if="can.viewChores">
                    <CardHeader>
                        <CardTitle>Today's Chores</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div v-if="todaysChores.length === 0" class="text-sm text-muted-foreground py-4 text-center">
                            No chores scheduled for today.
                        </div>
                        <ul v-else class="space-y-3">
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
                    </CardContent>
                </Card>

                <!-- Pinned Lists -->
                <template v-if="can.viewLists">
                <Card v-for="list in pinnedLists" :key="`list-${list.id}`">
                    <CardHeader class="flex-row items-center justify-between space-y-0">
                        <CardTitle>
                            <a :href="`/lists/${list.id}`" class="hover:underline">{{ list.name }}</a>
                        </CardTitle>
                        <span class="text-xs text-muted-foreground">{{ pinnedListProgress(list) }} done</span>
                    </CardHeader>
                    <CardContent>
                        <div v-if="!list.items || list.items.length === 0" class="text-sm text-muted-foreground py-4 text-center">
                            No items in this list.
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
                    </CardContent>
                </Card>
                </template>
            </div>
        </div>
    </AppLayout>
</template>
