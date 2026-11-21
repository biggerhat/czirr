<script setup lang="ts">
import { ArrowDownRight, ArrowUpRight } from 'lucide-vue-next';
import { computed } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

export interface Insights {
    trend: {
        month: string;
        label: string;
        income: number;
        spent: number;
        saved: number;
    }[];
    categories: {
        name: string;
        color: string | null;
        total: number;
        previous: number;
        share: number;
        change: number | null;
    }[];
    average_spent: number;
    month: string;
}

const props = defineProps<{ insights: Insights }>();

const compact = (n: number) =>
    new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'USD',
        notation: 'compact',
        maximumFractionDigits: 1,
    }).format(n);

const money = (n: number) =>
    n.toLocaleString(undefined, {
        style: 'currency',
        currency: 'USD',
        maximumFractionDigits: 0,
    });

const peak = computed(() =>
    Math.max(1, ...props.insights.trend.flatMap((t) => [t.income, t.spent])),
);
const pct = (n: number) => `${Math.max(2, (n / peak.value) * 100)}%`;
const hasData = computed(() =>
    props.insights.trend.some((t) => t.income > 0 || t.spent > 0),
);
</script>

<template>
    <div v-if="hasData" class="grid gap-4 lg:grid-cols-2">
        <Card>
            <CardHeader class="pb-2">
                <CardTitle class="text-base">Last 6 months</CardTitle>
                <p class="text-xs text-muted-foreground">
                    Average spend {{ money(insights.average_spent) }} a month
                </p>
            </CardHeader>
            <CardContent>
                <div
                    class="flex h-40 items-end gap-3"
                    role="img"
                    :aria-label="`Income and spending for the last ${insights.trend.length} months`"
                >
                    <div
                        v-for="t in insights.trend"
                        :key="t.month"
                        class="flex h-full flex-1 flex-col items-center justify-end gap-1"
                        :class="''"
                    >
                        <div
                            class="flex h-full w-full items-end justify-center gap-1"
                        >
                            <div
                                class="w-1/2 max-w-5 rounded-t bg-emerald-500"
                                :style="{
                                    height: t.income > 0 ? pct(t.income) : '0%',
                                }"
                                :title="`Income ${money(t.income)}`"
                            />
                            <div
                                class="w-1/2 max-w-5 rounded-t bg-primary"
                                :style="{
                                    height: t.spent > 0 ? pct(t.spent) : '0%',
                                }"
                                :title="`Spent ${money(t.spent)}`"
                            />
                        </div>
                        <span
                            class="text-xs text-muted-foreground"
                            :class="{
                                'font-semibold text-foreground':
                                    t.month === insights.month,
                            }"
                        >
                            {{ t.label }}
                        </span>
                        <span
                            class="text-[10px] leading-none tabular-nums text-muted-foreground"
                            aria-hidden="true"
                        >
                            {{ compact(t.spent) }}
                        </span>
                    </div>
                </div>
                <div
                    class="mt-3 flex items-center gap-4 text-xs text-muted-foreground"
                >
                    <span class="flex items-center gap-1"
                        ><span class="h-2 w-2 rounded-sm bg-emerald-500" />
                        Income</span
                    >
                    <span class="flex items-center gap-1"
                        ><span class="h-2 w-2 rounded-sm bg-primary" />
                        Spent</span
                    >
                </div>
                <table class="sr-only">
                    <caption>
                        Monthly income, spending and savings
                    </caption>
                    <tr v-for="t in insights.trend" :key="t.month">
                        <th>{{ t.label }}</th>
                        <td>Income {{ money(t.income) }}</td>
                        <td>Spent {{ money(t.spent) }}</td>
                        <td>Saved {{ money(t.saved) }}</td>
                    </tr>
                </table>
            </CardContent>
        </Card>

        <Card>
            <CardHeader class="pb-2">
                <CardTitle class="text-base"
                    >Where it went this month</CardTitle
                >
                <p class="text-xs text-muted-foreground">
                    Change is versus last month
                </p>
            </CardHeader>
            <CardContent>
                <p
                    v-if="insights.categories.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    No spending recorded yet this month.
                </p>
                <ul v-else class="space-y-2.5">
                    <li
                        v-for="c in insights.categories.slice(0, 6)"
                        :key="c.name"
                    >
                        <div
                            class="flex items-baseline justify-between gap-2 text-sm"
                        >
                            <span class="truncate font-medium">{{
                                c.name
                            }}</span>
                            <span
                                class="flex shrink-0 items-center gap-2 tabular-nums"
                            >
                                <span
                                    v-if="c.change !== null"
                                    class="flex items-center text-xs"
                                    :class="
                                        c.change > 0
                                            ? 'text-amber-700 dark:text-amber-400'
                                            : 'text-emerald-700 dark:text-emerald-400'
                                    "
                                >
                                    <component
                                        :is="
                                            c.change > 0
                                                ? ArrowUpRight
                                                : ArrowDownRight
                                        "
                                        class="h-3 w-3"
                                    />
                                    {{ Math.abs(c.change) }}%
                                </span>
                                {{ money(c.total) }}
                            </span>
                        </div>
                        <div
                            class="mt-1 h-1.5 overflow-hidden rounded-full bg-muted"
                        >
                            <div
                                class="h-full rounded-full bg-primary"
                                :style="{ width: `${c.share}%` }"
                            />
                        </div>
                    </li>
                </ul>
            </CardContent>
        </Card>
    </div>
</template>
