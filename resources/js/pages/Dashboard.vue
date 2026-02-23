<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type { UpcomingBill } from '@/types/budgeting';
import type { CalendarEvent } from '@/types/calendar';
import type { ChoreAssignment } from '@/types/chores';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Spinner } from '@/components/ui/spinner';
import { EVENT_COLORS, getEventColor, formatEventTime, formatEventDate } from '@/lib/calendar';
const props = defineProps<{
    upcomingBills: UpcomingBill[];
    todaysChores: ChoreAssignment[];
}>();

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
            </div>
        </div>
    </AppLayout>
</template>
