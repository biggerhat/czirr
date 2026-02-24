<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type { UpcomingBill } from '@/types/budgeting';
import type { CalendarEvent } from '@/types/calendar';
import type { ChoreAssignment } from '@/types/chores';
import type { FamilyList, FamilyListItem } from '@/types/lists';
import type { MealType } from '@/types/meal-plans';
import { MEAL_TYPE_LABELS } from '@/types/meal-plans';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { Spinner } from '@/components/ui/spinner';
import { CookingPot } from 'lucide-vue-next';
import { EVENT_COLORS, getEventColor, formatEventTime, formatEventDate } from '@/lib/calendar';

type DashboardMeal = {
    id: number;
    meal_type: MealType;
    name: string;
    recipe_id: number | null;
};

const props = defineProps<{
    upcomingBills: UpcomingBill[];
    todaysChores: ChoreAssignment[];
    todaysMeals: DashboardMeal[];
    pinnedLists: FamilyList[];
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

function getDueBadge(bill: UpcomingBill): { label: string; variant: 'default' | 'secondary' | 'destructive' | 'outline' } {
    if (bill.is_paid_this_month) {
        return { label: 'Paid', variant: 'secondary' };
    }

    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const due = new Date(bill.next_due_date + 'T00:00:00');
    const diffMs = due.getTime() - today.getTime();
    const diffDays = Math.round(diffMs / (1000 * 60 * 60 * 24));

    if (diffDays === 0) {
        return { label: 'Due today', variant: 'destructive' };
    }
    if (diffDays === 1) {
        return { label: 'Due tomorrow', variant: 'destructive' };
    }
    if (diffDays <= 3) {
        return { label: `In ${diffDays} days`, variant: 'destructive' };
    }
    return { label: `In ${diffDays} days`, variant: 'outline' };
}

function formatAmount(amount: number): string {
    return new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD' }).format(amount);
}

function formatBillDueDate(dateStr: string): string {
    const date = new Date(dateStr + 'T00:00:00');
    return date.toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' });
}

async function loadEvents() {
    eventsLoading.value = true;
    try {
        const start = new Date();
        start.setHours(0, 0, 0, 0);
        const end = new Date(start);
        end.setDate(end.getDate() + 7);
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

onMounted(loadEvents);

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
                <!-- Upcoming Bills -->
                <Card>
                    <CardHeader>
                        <CardTitle>Upcoming Bills</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div v-if="upcomingBills.length === 0" class="text-sm text-muted-foreground py-4 text-center">
                            No bills due in the next 14 days.
                        </div>
                        <ul v-else class="space-y-3">
                            <li
                                v-for="bill in upcomingBills"
                                :key="bill.id"
                                class="flex items-center gap-3"
                            >
                                <span
                                    class="size-2.5 shrink-0 rounded-full"
                                    :class="EVENT_COLORS[bill.category.color]?.dot ?? 'bg-gray-400'"
                                />
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm font-medium truncate">{{ bill.name }}</div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ formatBillDueDate(bill.next_due_date) }}
                                    </div>
                                </div>
                                <div class="text-sm font-medium tabular-nums">
                                    {{ formatAmount(bill.amount) }}
                                </div>
                                <Badge :variant="getDueBadge(bill).variant">
                                    {{ getDueBadge(bill).label }}
                                </Badge>
                            </li>
                        </ul>
                    </CardContent>
                </Card>

                <!-- Upcoming Events -->
                <Card>
                    <CardHeader>
                        <CardTitle>Upcoming Events</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div v-if="eventsLoading" class="flex items-center justify-center py-4">
                            <Spinner class="size-5" />
                        </div>
                        <div v-else-if="events.length === 0" class="text-sm text-muted-foreground py-4 text-center">
                            No events in the next 7 days.
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
                                        {{ formatEventDate(event.starts_at, timezone) }}
                                        <template v-if="!event.is_all_day">
                                            &middot; {{ formatEventTime(event.starts_at, timezone) }}
                                        </template>
                                        <template v-else>
                                            &middot; All day
                                        </template>
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </CardContent>
                </Card>

                <!-- Today's Meals -->
                <Card>
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
                <Card>
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
            </div>
        </div>
    </AppLayout>
</template>
