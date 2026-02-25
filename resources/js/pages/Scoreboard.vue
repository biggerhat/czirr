<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    ChevronLeft,
    ChevronRight,
    Crown,
    Flame,
    Gift,
    Medal,
    Plus,
    Star,
    Trophy,
} from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import { EVENT_COLORS } from '@/lib/calendar';
import type { BreadcrumbItem } from '@/types';
import type { FamilyMember } from '@/types/calendar';
import type {
    BonusObjective,
    MemberScore,
    OverallScore,
    StreakMilestone,
} from '@/types/chores';

const props = defineProps<{
    scoreboard: {
        weekly: MemberScore[];
        overall: OverallScore[];
        milestones: StreakMilestone[];
    };
    bonusObjectives: (BonusObjective & {
        claimed_by_member?: FamilyMember | null;
    })[];
    weekStart: string;
    can: {
        create: boolean;
        edit: boolean;
        delete: boolean;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Scoreboard' }];

const medalColors = ['text-yellow-500', 'text-gray-400', 'text-amber-700'];
const rankBgClasses = [
    'bg-yellow-50 dark:bg-yellow-900/20 border-yellow-200 dark:border-yellow-800',
    'bg-gray-50 dark:bg-gray-800/30 border-gray-200 dark:border-gray-700',
    'bg-amber-50 dark:bg-amber-900/20 border-amber-200 dark:border-amber-800',
];

function formatWeekLabel(dateStr: string): string {
    const d = new Date(dateStr + 'T00:00:00');
    const end = new Date(d);
    end.setDate(end.getDate() + 6);
    const opts: Intl.DateTimeFormatOptions = { month: 'short', day: 'numeric' };
    return `${d.toLocaleDateString(undefined, opts)} \u2013 ${end.toLocaleDateString(undefined, opts)}`;
}

function navigateWeek(offset: number) {
    const d = new Date(props.weekStart + 'T00:00:00');
    d.setDate(d.getDate() + offset * 7);
    router.visit(
        `/scoreboard?week=${d.toISOString().slice(0, 10)}`,
        { preserveState: true },
    );
}

const weeklyLeader = computed(() => props.scoreboard.weekly[0] ?? null);
const availableObjectives = computed(() =>
    props.bonusObjectives.filter((o) => !o.claimed_by),
);
const claimedObjectives = computed(() =>
    props.bonusObjectives.filter((o) => o.claimed_by),
);

// Create bonus dialog
const showCreateBonus = ref(false);
const bonusName = ref('');
const bonusDescription = ref('');
const bonusPoints = ref(25);
const isSavingBonus = ref(false);
const bonusErrors = ref<Record<string, string[]>>({});

function xsrfHeaders(): HeadersInit {
    return {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': decodeURIComponent(
            document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
        ),
    };
}

async function createBonus() {
    isSavingBonus.value = true;
    bonusErrors.value = {};

    try {
        const response = await fetch('/bonus-objectives', {
            method: 'POST',
            headers: xsrfHeaders(),
            body: JSON.stringify({
                name: bonusName.value,
                description: bonusDescription.value || null,
                points: bonusPoints.value,
            }),
        });

        if (response.ok) {
            showCreateBonus.value = false;
            bonusName.value = '';
            bonusDescription.value = '';
            bonusPoints.value = 25;
            router.reload();
        } else if (response.status === 422) {
            const data = await response.json();
            bonusErrors.value = data.errors ?? {};
        }
    } finally {
        isSavingBonus.value = false;
    }
}

// Claim dialog
const showClaimDialog = ref(false);
const claimingObjective = ref<BonusObjective | null>(null);
const claimMemberId = ref<string>('');
const isClaiming = ref(false);

function openClaim(objective: BonusObjective) {
    claimingObjective.value = objective;
    claimMemberId.value = '';
    showClaimDialog.value = true;
}

async function confirmClaim() {
    if (!claimingObjective.value || !claimMemberId.value) return;
    isClaiming.value = true;

    try {
        const response = await fetch(
            `/bonus-objectives/${claimingObjective.value.id}/claim`,
            {
                method: 'POST',
                headers: xsrfHeaders(),
                body: JSON.stringify({
                    family_member_id: parseInt(claimMemberId.value),
                }),
            },
        );

        if (response.ok) {
            showClaimDialog.value = false;
            router.reload();
        }
    } finally {
        isClaiming.value = false;
    }
}

async function deleteObjective(id: number) {
    await fetch(`/bonus-objectives/${id}`, {
        method: 'DELETE',
        headers: xsrfHeaders(),
    });
    router.reload();
}

// Get family members from the scoreboard data for the claim selector
const familyMembers = computed(() => {
    return [...props.scoreboard.weekly, ...props.scoreboard.overall].reduce(
        (acc, s) => {
            if (!acc.find((m) => m.id === s.family_member_id)) {
                acc.push({
                    id: s.family_member_id,
                    name: s.name,
                    color: s.color,
                });
            }
            return acc;
        },
        [] as { id: number; name: string; color: string }[],
    );
});

function nextMilestone(streak: number): StreakMilestone | null {
    return (
        props.scoreboard.milestones.find((m) => m.days_required > streak) ??
        null
    );
}

function earnedMilestones(streak: number): StreakMilestone[] {
    return props.scoreboard.milestones.filter(
        (m) => streak >= m.days_required,
    );
}

function streakFlameColor(streak: number): string {
    if (streak >= 14) return 'text-red-500';
    if (streak >= 7) return 'text-orange-500';
    if (streak >= 3) return 'text-amber-500';
    return 'text-muted-foreground';
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <!-- Header -->
            <div class="flex items-center justify-between gap-2">
                <h2
                    class="flex items-center gap-2 text-lg font-semibold"
                >
                    <Trophy class="h-5 w-5" />
                    Scoreboard
                </h2>
            </div>

            <!-- Week Navigation -->
            <div class="flex items-center justify-center gap-3">
                <Button
                    variant="outline"
                    size="icon"
                    class="h-8 w-8"
                    @click="navigateWeek(-1)"
                >
                    <ChevronLeft class="h-4 w-4" />
                </Button>
                <span
                    class="min-w-[180px] text-center text-sm font-medium"
                >
                    {{ formatWeekLabel(weekStart) }}
                </span>
                <Button
                    variant="outline"
                    size="icon"
                    class="h-8 w-8"
                    @click="navigateWeek(1)"
                >
                    <ChevronRight class="h-4 w-4" />
                </Button>
            </div>

            <!-- Hero: Weekly Leader -->
            <div
                v-if="weeklyLeader && weeklyLeader.weekly_total > 0"
                class="relative overflow-hidden rounded-xl border-2 border-yellow-300 bg-gradient-to-br from-yellow-50 via-amber-50 to-orange-50 p-3 sm:p-5 dark:border-yellow-700 dark:from-yellow-900/20 dark:via-amber-900/15 dark:to-orange-900/10"
            >
                <div
                    class="absolute -right-4 -top-4 hidden text-yellow-200/30 sm:block dark:text-yellow-700/20"
                >
                    <Trophy class="h-28 w-28" />
                </div>
                <div
                    class="relative flex items-center gap-3 sm:gap-4"
                >
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-full bg-yellow-100 sm:h-14 sm:w-14 dark:bg-yellow-800/40"
                    >
                        <Crown
                            class="h-5 w-5 text-yellow-600 sm:h-7 sm:w-7 dark:text-yellow-400"
                        />
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="text-[10px] font-medium uppercase tracking-wider text-yellow-600 sm:text-xs dark:text-yellow-400">
                            Weekly Leader
                        </div>
                        <div
                            class="flex items-center gap-1.5 text-base font-bold sm:gap-2 sm:text-xl"
                        >
                            <span
                                class="h-2.5 w-2.5 shrink-0 rounded-full sm:h-3 sm:w-3"
                                :class="EVENT_COLORS[weeklyLeader.color as keyof typeof EVENT_COLORS]?.dot ?? 'bg-gray-400'"
                            />
                            <span class="truncate">{{ weeklyLeader.name }}</span>
                        </div>
                        <div
                            class="text-xs text-muted-foreground sm:text-sm"
                        >
                            {{ weeklyLeader.chore_points }}
                            chores
                            <template
                                v-if="weeklyLeader.bonus_points"
                            >
                                +
                                {{ weeklyLeader.bonus_points }}
                                bonus</template
                            >
                            <template
                                v-if="weeklyLeader.streak_bonus"
                            >
                                +
                                {{ weeklyLeader.streak_bonus }}
                                streak</template
                            >
                        </div>
                    </div>
                    <div class="shrink-0 text-right">
                        <div
                            class="text-2xl font-bold tabular-nums text-yellow-700 sm:text-3xl dark:text-yellow-300"
                        >
                            {{ weeklyLeader.weekly_total }}
                        </div>
                        <div
                            class="text-[10px] font-medium text-yellow-600 sm:text-xs dark:text-yellow-400"
                        >
                            points
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <!-- Weekly Leaderboard -->
                <Card>
                    <CardHeader>
                        <CardTitle
                            class="flex items-center gap-2"
                        >
                            <Star class="h-4 w-4" />
                            Weekly Leaderboard
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div
                            v-if="scoreboard.weekly.length === 0"
                            class="py-8 text-center"
                        >
                            <Star
                                class="mx-auto mb-2 h-8 w-8 text-muted-foreground/30"
                            />
                            <p
                                class="text-sm text-muted-foreground"
                            >
                                No scores this week yet.
                            </p>
                            <p
                                class="mt-1 text-xs text-muted-foreground/70"
                            >
                                Complete chores to earn
                                points!
                            </p>
                        </div>
                        <div v-else class="space-y-2">
                            <div
                                v-for="(score, i) in scoreboard.weekly"
                                :key="score.family_member_id"
                                class="flex items-center gap-2 rounded-lg border px-2.5 py-2 transition-colors sm:gap-3 sm:px-3 sm:py-2.5"
                                :class="i < 3 ? rankBgClasses[i] : 'border-transparent'"
                            >
                                <div
                                    class="w-5 shrink-0 text-center sm:w-6"
                                >
                                    <Medal
                                        v-if="i < 3"
                                        class="mx-auto h-4 w-4 sm:h-5 sm:w-5"
                                        :class="medalColors[i]"
                                    />
                                    <span
                                        v-else
                                        class="text-xs text-muted-foreground sm:text-sm"
                                        >{{
                                            i + 1
                                        }}</span
                                    >
                                </div>
                                <span
                                    class="h-2.5 w-2.5 shrink-0 rounded-full sm:h-3 sm:w-3"
                                    :class="EVENT_COLORS[score.color as keyof typeof EVENT_COLORS]?.dot ?? 'bg-gray-400'"
                                />
                                <span
                                    class="min-w-0 flex-1 truncate text-sm font-medium"
                                    >{{
                                        score.name
                                    }}</span
                                >
                                <div
                                    class="shrink-0 text-right"
                                >
                                    <div
                                        class="text-sm font-semibold tabular-nums"
                                    >
                                        {{
                                            score.weekly_total
                                        }}
                                        pts
                                    </div>
                                    <div
                                        class="hidden text-xs text-muted-foreground sm:block"
                                    >
                                        {{
                                            score.chore_points
                                        }}
                                        chores
                                        <template
                                            v-if="score.bonus_points"
                                        >
                                            +
                                            {{
                                                score.bonus_points
                                            }}
                                            bonus</template
                                        >
                                        <template
                                            v-if="score.streak_bonus"
                                        >
                                            +
                                            {{
                                                score.streak_bonus
                                            }}
                                            streak</template
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Overall Leaderboard -->
                <Card>
                    <CardHeader>
                        <CardTitle
                            class="flex items-center gap-2"
                        >
                            <Trophy class="h-4 w-4" />
                            All-Time Leaderboard
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div
                            v-if="scoreboard.overall.length === 0"
                            class="py-8 text-center"
                        >
                            <Trophy
                                class="mx-auto mb-2 h-8 w-8 text-muted-foreground/30"
                            />
                            <p
                                class="text-sm text-muted-foreground"
                            >
                                No scores yet.
                            </p>
                        </div>
                        <div v-else class="space-y-2">
                            <div
                                v-for="(score, i) in scoreboard.overall"
                                :key="score.family_member_id"
                                class="flex items-center gap-2 rounded-lg border px-2.5 py-2 transition-colors sm:gap-3 sm:px-3 sm:py-2.5"
                                :class="i < 3 ? rankBgClasses[i] : 'border-transparent'"
                            >
                                <div
                                    class="w-5 shrink-0 text-center sm:w-6"
                                >
                                    <Medal
                                        v-if="i < 3"
                                        class="mx-auto h-4 w-4 sm:h-5 sm:w-5"
                                        :class="medalColors[i]"
                                    />
                                    <span
                                        v-else
                                        class="text-xs text-muted-foreground sm:text-sm"
                                        >{{
                                            i + 1
                                        }}</span
                                    >
                                </div>
                                <span
                                    class="h-2.5 w-2.5 shrink-0 rounded-full sm:h-3 sm:w-3"
                                    :class="EVENT_COLORS[score.color as keyof typeof EVENT_COLORS]?.dot ?? 'bg-gray-400'"
                                />
                                <span
                                    class="min-w-0 flex-1 truncate text-sm font-medium"
                                    >{{
                                        score.name
                                    }}</span
                                >
                                <span
                                    class="shrink-0 text-sm font-semibold tabular-nums"
                                    >{{
                                        score.total
                                    }}
                                    pts</span
                                >
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Streaks -->
                <Card>
                    <CardHeader>
                        <CardTitle
                            class="flex items-center gap-2"
                        >
                            <Flame class="h-4 w-4" />
                            Streaks
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div
                            v-if="scoreboard.weekly.length === 0"
                            class="py-8 text-center"
                        >
                            <Flame
                                class="mx-auto mb-2 h-8 w-8 text-muted-foreground/30"
                            />
                            <p
                                class="text-sm text-muted-foreground"
                            >
                                No streak data yet.
                            </p>
                            <p
                                class="mt-1 text-xs text-muted-foreground/70"
                            >
                                Complete all daily chores
                                to build a streak!
                            </p>
                        </div>
                        <div v-else class="space-y-5">
                            <div
                                v-for="score in scoreboard.weekly"
                                :key="score.family_member_id"
                            >
                                <div
                                    class="mb-2 flex items-center gap-2"
                                >
                                    <span
                                        class="h-2.5 w-2.5 shrink-0 rounded-full"
                                        :class="EVENT_COLORS[score.color as keyof typeof EVENT_COLORS]?.dot ?? 'bg-gray-400'"
                                    />
                                    <span
                                        class="text-sm font-medium"
                                        >{{
                                            score.name
                                        }}</span
                                    >
                                    <span
                                        class="ml-auto flex items-center gap-1 text-sm font-semibold"
                                    >
                                        <Flame
                                            v-if="score.streak >= 3"
                                            class="h-4 w-4"
                                            :class="streakFlameColor(score.streak)"
                                        />
                                        {{
                                            score.streak
                                        }}
                                        day{{
                                            score.streak !== 1
                                                ? 's'
                                                : ''
                                        }}
                                    </span>
                                </div>
                                <!-- Segmented progress bar -->
                                <div
                                    class="flex gap-0.5"
                                >
                                    <div
                                        v-for="(
                                            milestone, mi
                                        ) in scoreboard.milestones"
                                        :key="milestone.id"
                                        class="relative h-3 flex-1 overflow-hidden rounded-full"
                                        :class="mi === 0 ? 'rounded-l-full' : mi === scoreboard.milestones.length - 1 ? 'rounded-r-full' : ''"
                                    >
                                        <div
                                            class="absolute inset-0 bg-muted"
                                        />
                                        <div
                                            v-if="score.streak >= milestone.days_required"
                                            class="absolute inset-0 rounded-full bg-gradient-to-r from-orange-400 to-orange-500"
                                        />
                                        <div
                                            v-else-if="mi === 0 ? score.streak > 0 : score.streak > (scoreboard.milestones[mi - 1]?.days_required ?? 0)"
                                            class="absolute inset-0 rounded-full bg-gradient-to-r from-orange-400 to-orange-500"
                                            :style="{
                                                width:
                                                    (() => {
                                                        const prev =
                                                            mi ===
                                                            0
                                                                ? 0
                                                                : scoreboard
                                                                      .milestones[
                                                                      mi -
                                                                          1
                                                                  ]
                                                                      ?.days_required ??
                                                                  0;
                                                        const range =
                                                            milestone.days_required -
                                                            prev;
                                                        const progress =
                                                            score.streak -
                                                            prev;
                                                        return `${Math.min(100, (progress / range) * 100)}%`;
                                                    })(),
                                            }"
                                        />
                                    </div>
                                </div>
                                <!-- Milestone labels -->
                                <div
                                    class="mt-1 flex flex-wrap items-center gap-1"
                                >
                                    <Badge
                                        v-for="milestone in earnedMilestones(
                                            score.streak,
                                        )"
                                        :key="milestone.id"
                                        variant="secondary"
                                        class="text-[10px] px-1.5 py-0"
                                    >
                                        {{
                                            milestone.days_required
                                        }}d +{{
                                            milestone.bonus_points
                                        }}pts
                                    </Badge>
                                    <span
                                        v-if="nextMilestone(score.streak)"
                                        class="ml-auto text-xs text-muted-foreground"
                                    >
                                        Next:
                                        {{
                                            nextMilestone(
                                                score.streak,
                                            )!.days_required
                                        }}
                                        days (+{{
                                            nextMilestone(
                                                score.streak,
                                            )!.bonus_points
                                        }}
                                        pts)
                                    </span>
                                    <span
                                        v-else-if="score.streak > 0"
                                        class="ml-auto text-xs text-muted-foreground"
                                    >
                                        All milestones
                                        reached!
                                    </span>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Bonus Objectives -->
                <Card>
                    <CardHeader
                        class="flex-row items-center justify-between space-y-0"
                    >
                        <CardTitle
                            class="flex items-center gap-2"
                        >
                            <Gift class="h-4 w-4" />
                            Bonus Objectives
                        </CardTitle>
                        <Button
                            v-if="can.create"
                            size="sm"
                            variant="outline"
                            @click="showCreateBonus = true"
                        >
                            <Plus class="mr-1 h-3.5 w-3.5" />
                            Add
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <!-- Available -->
                        <div
                            v-if="availableObjectives.length > 0"
                            class="mb-4 space-y-2"
                        >
                            <div
                                v-for="obj in availableObjectives"
                                :key="obj.id"
                                class="rounded-lg border-2 border-dashed border-primary/20 bg-primary/5 p-2.5 transition-colors hover:border-primary/40 hover:bg-primary/10 sm:p-3"
                            >
                                <div class="flex items-center gap-2 sm:gap-3">
                                    <div
                                        class="hidden h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary/10 sm:flex"
                                    >
                                        <Star
                                            class="h-4 w-4 text-primary"
                                        />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div
                                            class="truncate text-sm font-medium"
                                        >
                                            {{ obj.name }}
                                        </div>
                                        <div
                                            v-if="obj.description"
                                            class="mt-0.5 hidden text-xs text-muted-foreground sm:block"
                                        >
                                            {{
                                                obj.description
                                            }}
                                        </div>
                                    </div>
                                    <Badge
                                        class="shrink-0 bg-primary/10 text-primary"
                                        >{{
                                            obj.points
                                        }}
                                        pts</Badge
                                    >
                                    <div
                                        class="flex shrink-0 gap-1"
                                    >
                                        <Button
                                            size="sm"
                                            @click="openClaim(obj)"
                                            >Claim</Button
                                        >
                                        <Button
                                            v-if="can.delete"
                                            size="sm"
                                            variant="ghost"
                                            class="h-8 w-8 p-0 text-destructive"
                                            @click="deleteObjective(obj.id)"
                                        >
                                            &times;
                                        </Button>
                                    </div>
                                </div>
                                <div
                                    v-if="obj.description"
                                    class="mt-1 text-xs text-muted-foreground sm:hidden"
                                >
                                    {{ obj.description }}
                                </div>
                            </div>
                        </div>
                        <div
                            v-else
                            class="mb-4 py-6 text-center"
                        >
                            <Gift
                                class="mx-auto mb-2 h-8 w-8 text-muted-foreground/30"
                            />
                            <p
                                class="text-sm text-muted-foreground"
                            >
                                No bonus objectives
                                available.
                            </p>
                            <p
                                v-if="can.create"
                                class="mt-1 text-xs text-muted-foreground/70"
                            >
                                Add one for extra points!
                            </p>
                        </div>

                        <!-- Claimed -->
                        <div v-if="claimedObjectives.length > 0">
                            <div
                                class="mb-2 text-xs font-medium uppercase tracking-wider text-muted-foreground"
                            >
                                Completed
                            </div>
                            <div class="space-y-1.5">
                                <div
                                    v-for="obj in claimedObjectives"
                                    :key="obj.id"
                                    class="flex items-center gap-1.5 rounded-md bg-muted/50 px-2.5 py-2 text-sm text-muted-foreground sm:gap-2 sm:px-3"
                                >
                                    <span
                                        class="flex-1 truncate line-through"
                                        >{{
                                            obj.name
                                        }}</span
                                    >
                                    <span
                                        class="shrink-0 text-xs"
                                    >
                                        {{
                                            obj.claimed_by_member
                                                ?.nickname ||
                                            obj
                                                .claimed_by_member
                                                ?.name ||
                                            'Unknown'
                                        }}
                                    </span>
                                    <Badge
                                        variant="outline"
                                        class="shrink-0 text-xs"
                                        >{{
                                            obj.points
                                        }}
                                        pts</Badge
                                    >
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Create Bonus Dialog -->
            <Dialog
                :open="showCreateBonus"
                @update:open="showCreateBonus = $event"
            >
                <DialogContent class="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle
                            >Create Bonus
                            Objective</DialogTitle
                        >
                    </DialogHeader>
                    <form
                        class="space-y-4"
                        @submit.prevent="createBonus"
                    >
                        <div class="space-y-2">
                            <Label for="bonus-name"
                                >Name</Label
                            >
                            <Input
                                id="bonus-name"
                                v-model="bonusName"
                                placeholder="e.g. Clean the garage"
                                required
                            />
                            <p
                                v-if="bonusErrors.name"
                                class="text-sm text-destructive"
                            >
                                {{
                                    bonusErrors.name[0]
                                }}
                            </p>
                        </div>
                        <div class="space-y-2">
                            <Label for="bonus-desc"
                                >Description</Label
                            >
                            <Textarea
                                id="bonus-desc"
                                v-model="bonusDescription"
                                placeholder="Optional details"
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="bonus-points"
                                >Points</Label
                            >
                            <Input
                                id="bonus-points"
                                v-model.number="bonusPoints"
                                type="number"
                                min="1"
                                max="10000"
                            />
                            <p
                                v-if="bonusErrors.points"
                                class="text-sm text-destructive"
                            >
                                {{
                                    bonusErrors
                                        .points[0]
                                }}
                            </p>
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                @click="showCreateBonus = false"
                                >Cancel</Button
                            >
                            <Button
                                type="submit"
                                :disabled="isSavingBonus"
                            >
                                {{
                                    isSavingBonus
                                        ? 'Creating...'
                                        : 'Create'
                                }}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <!-- Claim Dialog -->
            <Dialog
                :open="showClaimDialog"
                @update:open="showClaimDialog = $event"
            >
                <DialogContent class="sm:max-w-sm">
                    <DialogHeader>
                        <DialogTitle
                            >Claim "{{
                                claimingObjective?.name
                            }}"</DialogTitle
                        >
                    </DialogHeader>
                    <div class="space-y-4">
                        <div class="space-y-2">
                            <Label
                                >Who completed
                                this?</Label
                            >
                            <Select v-model="claimMemberId">
                                <SelectTrigger>
                                    <SelectValue
                                        placeholder="Select family member"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="m in familyMembers"
                                        :key="m.id"
                                        :value="String(m.id)"
                                    >
                                        {{ m.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            @click="showClaimDialog = false"
                            >Cancel</Button
                        >
                        <Button
                            :disabled="!claimMemberId || isClaiming"
                            @click="confirmClaim"
                        >
                            {{
                                isClaiming
                                    ? 'Claiming...'
                                    : 'Claim'
                            }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>
