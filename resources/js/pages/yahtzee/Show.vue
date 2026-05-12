<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ChevronLeft, Circle, Dices, Pin, Sparkles, Trophy } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { playerColorClasses } from '@/lib/yahtzee';
import type { BreadcrumbItem } from '@/types';
import type { EventColor } from '@/types/calendar';
import type {
    Scorecard,
    Totals,
    YahtzeeCategory,
    YahtzeeGame,
} from '@/types/yahtzee';
import {
    CATEGORY_LABELS,
    LOWER_CATEGORIES,
    UPPER_CATEGORIES,
} from '@/types/yahtzee';

const props = defineProps<{
    game: YahtzeeGame;
    preview: Partial<Record<YahtzeeCategory, number>>;
    totals: Record<number, Totals>;
    currentUserId: number;
    playerColors: Record<number, EventColor>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Yahtzee', href: '/yahtzee' },
    { title: `${props.game.player_one.name} vs ${props.game.player_two.name}` },
];

const isMyTurn = computed(
    () => props.game.status === 'active' && props.game.current_turn_user_id === props.currentUserId,
);

const canRoll = computed(() => isMyTurn.value && props.game.rolls_left > 0);

const canScore = computed(() => isMyTurn.value && props.game.rolls_left < 3);

const myScorecard = computed<Scorecard>(
    () => props.game.scorecards[props.currentUserId] ?? ({} as Scorecard),
);

const isRolling = ref(false);

type DieFace = {
    glyph: string;
    bg: string;
    border: string;
    text: string;
};

const DIE_FACES: Record<number, DieFace> = {
    1: {
        glyph: '⚀',
        bg: 'bg-rose-100 dark:bg-rose-900/40',
        border: 'border-rose-300 dark:border-rose-700',
        text: 'text-rose-700 dark:text-rose-300',
    },
    2: {
        glyph: '⚁',
        bg: 'bg-orange-100 dark:bg-orange-900/40',
        border: 'border-orange-300 dark:border-orange-700',
        text: 'text-orange-700 dark:text-orange-300',
    },
    3: {
        glyph: '⚂',
        bg: 'bg-amber-100 dark:bg-amber-900/40',
        border: 'border-amber-300 dark:border-amber-700',
        text: 'text-amber-700 dark:text-amber-300',
    },
    4: {
        glyph: '⚃',
        bg: 'bg-emerald-100 dark:bg-emerald-900/40',
        border: 'border-emerald-300 dark:border-emerald-700',
        text: 'text-emerald-700 dark:text-emerald-300',
    },
    5: {
        glyph: '⚄',
        bg: 'bg-sky-100 dark:bg-sky-900/40',
        border: 'border-sky-300 dark:border-sky-700',
        text: 'text-sky-700 dark:text-sky-300',
    },
    6: {
        glyph: '⚅',
        bg: 'bg-violet-100 dark:bg-violet-900/40',
        border: 'border-violet-300 dark:border-violet-700',
        text: 'text-violet-700 dark:text-violet-300',
    },
};

function pc(pid: number) {
    return playerColorClasses(props.playerColors[pid]);
}

const myColor = computed(() => pc(props.currentUserId));

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

function reload() {
    router.reload({ only: ['game', 'preview', 'totals'] });
}

async function rollDice() {
    if (!canRoll.value || isRolling.value) return;
    isRolling.value = true;
    await fetch(`/yahtzee/${props.game.id}/roll`, {
        method: 'POST',
        headers: xsrfHeaders(),
    });
    isRolling.value = false;
    reload();
}

async function toggleHold(index: number) {
    if (!isMyTurn.value || props.game.rolls_left === 3) return;
    await fetch(`/yahtzee/${props.game.id}/hold`, {
        method: 'PATCH',
        headers: xsrfHeaders(),
        body: JSON.stringify({ index }),
    });
    reload();
}

async function chooseCategory(category: YahtzeeCategory) {
    if (!canScore.value) return;
    const v = myScorecard.value[category];
    if (v !== null && v !== undefined) return;
    await fetch(`/yahtzee/${props.game.id}/score`, {
        method: 'POST',
        headers: xsrfHeaders(),
        body: JSON.stringify({ category }),
    });
    reload();
}

let pollTimer: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
    pollTimer = setInterval(() => {
        if (props.game.status === 'active' && !isMyTurn.value) {
            reload();
        }
    }, 2500);
});

onBeforeUnmount(() => {
    if (pollTimer) clearInterval(pollTimer);
});

type CellKind = 'filled' | 'preview-positive' | 'preview-zero' | 'empty-mine' | 'empty-opponent';

function cellKind(playerId: number, cat: YahtzeeCategory): CellKind {
    const v = props.game.scorecards[playerId]?.[cat];
    if (v !== null && v !== undefined) return 'filled';
    if (playerId !== props.currentUserId) return 'empty-opponent';
    if (!canScore.value || props.preview[cat] === undefined) return 'empty-mine';
    return props.preview[cat] === 0 ? 'preview-zero' : 'preview-positive';
}

function cellNumber(playerId: number, cat: YahtzeeCategory): number {
    const v = props.game.scorecards[playerId]?.[cat];
    if (v !== null && v !== undefined) return v;
    return props.preview[cat] ?? 0;
}

const bestCategory = computed<YahtzeeCategory | null>(() => {
    if (!canScore.value) return null;
    let best: YahtzeeCategory | null = null;
    let bestVal = 0;
    for (const cat of [...UPPER_CATEGORIES, ...LOWER_CATEGORIES]) {
        const v = props.preview[cat];
        if (v !== undefined && v > bestVal) {
            best = cat;
            bestVal = v;
        }
    }
    return best;
});

function isActiveColumn(playerId: number): boolean {
    return props.game.status === 'active' && props.game.current_turn_user_id === playerId;
}

const upperRemaining = computed(() => {
    const upper = props.totals[props.currentUserId]?.upper ?? 0;
    return Math.max(0, 63 - upper);
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="flex items-center justify-between gap-2">
                <Link href="/yahtzee" class="flex items-center gap-1 text-sm text-muted-foreground hover:underline">
                    <ChevronLeft class="h-4 w-4" />
                    Back to games
                </Link>
                <div class="text-xs text-muted-foreground">
                    Game #{{ game.id }}
                </div>
            </div>

            <!-- Status banner -->
            <div
                v-if="game.status === 'completed'"
                class="rounded-lg border-2 border-amber-300 bg-gradient-to-r from-amber-50 via-yellow-50 to-amber-50 p-4 text-center shadow-sm dark:border-amber-700 dark:from-amber-950/40 dark:via-yellow-950/40 dark:to-amber-950/40"
            >
                <Trophy class="mx-auto mb-2 h-7 w-7 text-amber-500" />
                <div class="text-lg font-semibold">
                    <template v-if="game.winner">
                        <span :class="pc(game.winner.id).text">{{ game.winner.name }}</span>
                        <span> wins!</span>
                    </template>
                    <template v-else>It's a tie!</template>
                </div>
            </div>

            <div
                v-else
                class="flex items-center justify-center gap-3 rounded-lg border-2 px-4 py-2 text-sm transition-colors"
                :class="isMyTurn
                    ? [myColor.softBorder, myColor.tint]
                    : 'border-transparent bg-muted/30'"
            >
                <span
                    class="inline-block h-2 w-2 rounded-full"
                    :class="isMyTurn
                        ? [myColor.dot, 'animate-pulse']
                        : 'bg-muted-foreground/40'"
                />
                <span v-if="isMyTurn" class="font-medium" :class="myColor.text">Your turn</span>
                <span v-else class="text-muted-foreground">
                    Waiting on
                    <span :class="pc(game.current_turn_user_id).text" class="font-medium">{{ game.current_turn_user.name }}</span>…
                </span>
                <span class="text-xs text-muted-foreground">·</span>
                <span class="text-xs text-muted-foreground">
                    {{ game.rolls_left }} {{ game.rolls_left === 1 ? 'roll' : 'rolls' }} left
                </span>
            </div>

            <!-- Dice -->
            <div class="rounded-lg border p-4 sm:p-6">
                <div class="flex items-center justify-center gap-3 sm:gap-4">
                    <button
                        v-for="(die, idx) in game.dice"
                        :key="idx"
                        type="button"
                        class="relative flex h-16 w-16 items-center justify-center rounded-2xl border-2 text-5xl font-bold transition-all sm:h-20 sm:w-20 sm:text-6xl"
                        :class="[
                            game.rolls_left === 3
                                ? 'border-muted bg-muted/20 text-muted-foreground/60'
                                : die.held
                                    ? ['scale-105 shadow-lg', myColor.softBorder, myColor.softBg, myColor.text, 'ring-2 ring-offset-2', myColor.ring]
                                    : ['shadow-md', DIE_FACES[die.value].border, DIE_FACES[die.value].bg, DIE_FACES[die.value].text],
                            isMyTurn && game.rolls_left < 3
                                ? 'cursor-pointer hover:-translate-y-0.5 hover:shadow-lg active:scale-100'
                                : 'cursor-default',
                            game.rolls_left === 3 ? 'opacity-50' : '',
                        ]"
                        :disabled="!isMyTurn || game.rolls_left === 3"
                        @click="toggleHold(idx)"
                    >
                        <span class="leading-none">
                            {{ game.rolls_left === 3 ? '?' : DIE_FACES[die.value].glyph }}
                        </span>
                        <Pin
                            v-if="die.held && game.rolls_left < 3"
                            class="absolute -right-1.5 -top-1.5 h-5 w-5 rounded-full bg-background p-0.5 shadow-sm"
                            :class="myColor.text"
                        />
                    </button>
                </div>
                <div class="mt-4 text-center text-xs text-muted-foreground">
                    <template v-if="isMyTurn && game.rolls_left < 3 && game.rolls_left > 0">
                        Tap a die to hold it for the next roll.
                    </template>
                    <template v-else-if="isMyTurn && game.rolls_left === 3">
                        Roll to start your turn.
                    </template>
                    <template v-else-if="isMyTurn && game.rolls_left === 0">
                        No rolls left — pick a category below to score.
                    </template>
                </div>
                <div class="mt-3 flex justify-center">
                    <Button :disabled="!canRoll || isRolling" size="lg" @click="rollDice">
                        <Dices class="mr-2 h-5 w-5" />
                        Roll ({{ game.rolls_left }} left)
                    </Button>
                </div>
            </div>

            <!-- Scorecard -->
            <div class="overflow-hidden rounded-lg border">
                <table class="w-full text-sm">
                    <!-- Player header strip -->
                    <thead>
                        <tr class="border-b">
                            <th class="bg-muted/40 px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                Scorecard
                            </th>
                            <th
                                v-for="pid in [game.player_one_id, game.player_two_id]"
                                :key="pid"
                                class="px-3 py-2 text-right transition-colors"
                                :class="[isActiveColumn(pid) ? pc(pid).tint : 'bg-muted/40']"
                            >
                                <div class="flex items-center justify-end gap-1.5">
                                    <span
                                        class="inline-block h-1.5 w-1.5 rounded-full"
                                        :class="[
                                            pc(pid).dot,
                                            isActiveColumn(pid) ? 'animate-pulse' : 'opacity-50',
                                        ]"
                                    />
                                    <span class="text-sm font-medium" :class="pc(pid).text">
                                        {{ pid === game.player_one_id ? game.player_one.name : game.player_two.name }}
                                    </span>
                                </div>
                                <div class="text-xl font-bold tabular-nums" :class="pc(pid).text">
                                    {{ totals[pid].grand }}
                                </div>
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <!-- Upper section header -->
                        <tr class="border-b border-t bg-muted/20">
                            <td colspan="3" class="px-3 py-1 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
                                Upper · count matching dice
                            </td>
                        </tr>

                        <!-- Upper rows -->
                        <tr v-for="cat in UPPER_CATEGORIES" :key="cat" class="border-b last:border-b-0">
                            <td class="px-3 py-2">{{ CATEGORY_LABELS[cat] }}</td>

                            <td
                                v-for="pid in [game.player_one_id, game.player_two_id]"
                                :key="pid"
                                class="px-3 py-1.5 text-right tabular-nums"
                                :class="isActiveColumn(pid) ? pc(pid).tint : ''"
                            >
                                <template v-if="cellKind(pid, cat) === 'filled'">
                                    <span class="font-medium">{{ cellNumber(pid, cat) }}</span>
                                </template>
                                <template v-else-if="cellKind(pid, cat) === 'preview-positive'">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1 rounded-md border px-2.5 py-1 text-sm font-semibold shadow-sm transition-all hover:-translate-y-0.5 hover:shadow active:translate-y-0 active:scale-95"
                                        :class="[myColor.softBorder, myColor.softBg, myColor.text, myColor.hoverBg]"
                                        @click="chooseCategory(cat)"
                                    >
                                        <Sparkles v-if="cat === bestCategory" class="h-3.5 w-3.5" />
                                        {{ cellNumber(pid, cat) }}
                                    </button>
                                </template>
                                <template v-else-if="cellKind(pid, cat) === 'preview-zero'">
                                    <button
                                        type="button"
                                        class="inline-flex items-center rounded-md border border-muted-foreground/20 bg-muted/30 px-2.5 py-1 text-xs text-muted-foreground/70 transition-all hover:bg-muted hover:text-muted-foreground active:scale-95"
                                        @click="chooseCategory(cat)"
                                    >
                                        <span class="line-through decoration-1">0</span>
                                    </button>
                                </template>
                                <template v-else>
                                    <span class="text-muted-foreground/40">—</span>
                                </template>
                            </td>
                        </tr>

                        <!-- Upper subtotal + bonus -->
                        <tr class="border-b bg-muted/20 text-xs">
                            <td class="px-3 py-1.5 italic text-muted-foreground">
                                Upper subtotal
                                <span v-if="isMyTurn && upperRemaining > 0" class="ml-1 text-[10px]">
                                    (need {{ upperRemaining }} more for bonus)
                                </span>
                            </td>
                            <td
                                v-for="pid in [game.player_one_id, game.player_two_id]"
                                :key="pid"
                                class="px-3 py-1.5 text-right tabular-nums"
                                :class="isActiveColumn(pid) ? pc(pid).tint : ''"
                            >
                                {{ totals[pid].upper }} / 63
                            </td>
                        </tr>
                        <tr class="border-b bg-muted/20 text-xs">
                            <td class="px-3 py-1.5 italic text-muted-foreground">Upper bonus (+35)</td>
                            <td
                                v-for="pid in [game.player_one_id, game.player_two_id]"
                                :key="pid"
                                class="px-3 py-1.5 text-right tabular-nums"
                                :class="[
                                    isActiveColumn(pid) ? pc(pid).tint : '',
                                    totals[pid].upper_bonus > 0
                                        ? ['font-semibold', pc(pid).text]
                                        : 'text-muted-foreground/50',
                                ]"
                            >
                                {{ totals[pid].upper_bonus > 0 ? `+${totals[pid].upper_bonus}` : '—' }}
                            </td>
                        </tr>

                        <!-- Lower section header -->
                        <tr class="border-b border-t bg-muted/20">
                            <td colspan="3" class="px-3 py-1 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
                                Lower · combinations
                            </td>
                        </tr>

                        <!-- Lower rows -->
                        <tr
                            v-for="cat in LOWER_CATEGORIES"
                            :key="cat"
                            class="border-b last:border-b-0"
                            :class="cat === 'yahtzee' ? 'bg-gradient-to-r from-amber-50/50 to-transparent dark:from-amber-950/20' : ''"
                        >
                            <td class="px-3 py-2">
                                <span :class="cat === 'yahtzee' ? 'font-semibold text-amber-700 dark:text-amber-300' : ''">
                                    {{ CATEGORY_LABELS[cat] }}
                                </span>
                                <span class="ml-1 text-[10px] text-muted-foreground/60">
                                    {{ cat === 'full_house' ? '25' :
                                        cat === 'small_straight' ? '30' :
                                        cat === 'large_straight' ? '40' :
                                        cat === 'yahtzee' ? '50' : '' }}
                                </span>
                            </td>

                            <td
                                v-for="pid in [game.player_one_id, game.player_two_id]"
                                :key="pid"
                                class="px-3 py-1.5 text-right tabular-nums"
                                :class="isActiveColumn(pid) ? pc(pid).tint : ''"
                            >
                                <template v-if="cellKind(pid, cat) === 'filled'">
                                    <span
                                        class="font-medium"
                                        :class="cat === 'yahtzee' && cellNumber(pid, cat) === 50 ? 'text-amber-600 dark:text-amber-400' : ''"
                                    >
                                        {{ cellNumber(pid, cat) }}
                                    </span>
                                </template>
                                <template v-else-if="cellKind(pid, cat) === 'preview-positive'">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1 rounded-md border px-2.5 py-1 text-sm font-semibold shadow-sm transition-all hover:-translate-y-0.5 hover:shadow active:translate-y-0 active:scale-95"
                                        :class="[myColor.softBorder, myColor.softBg, myColor.text, myColor.hoverBg]"
                                        @click="chooseCategory(cat)"
                                    >
                                        <Sparkles v-if="cat === bestCategory" class="h-3.5 w-3.5" />
                                        {{ cellNumber(pid, cat) }}
                                    </button>
                                </template>
                                <template v-else-if="cellKind(pid, cat) === 'preview-zero'">
                                    <button
                                        type="button"
                                        class="inline-flex items-center rounded-md border border-muted-foreground/20 bg-muted/30 px-2.5 py-1 text-xs text-muted-foreground/70 transition-all hover:bg-muted hover:text-muted-foreground active:scale-95"
                                        @click="chooseCategory(cat)"
                                    >
                                        <span class="line-through decoration-1">0</span>
                                    </button>
                                </template>
                                <template v-else>
                                    <span class="text-muted-foreground/40">—</span>
                                </template>
                            </td>
                        </tr>

                        <!-- Yahtzee bonus -->
                        <tr class="border-b bg-muted/20 text-xs">
                            <td class="px-3 py-1.5 italic text-muted-foreground">Yahtzee bonus (+100 each)</td>
                            <td
                                v-for="pid in [game.player_one_id, game.player_two_id]"
                                :key="pid"
                                class="px-3 py-1.5 text-right tabular-nums"
                                :class="[
                                    isActiveColumn(pid) ? pc(pid).tint : '',
                                    totals[pid].yahtzee_bonus > 0
                                        ? 'font-semibold text-amber-600 dark:text-amber-400'
                                        : 'text-muted-foreground/50',
                                ]"
                            >
                                {{ totals[pid].yahtzee_bonus > 0 ? `+${totals[pid].yahtzee_bonus}` : '—' }}
                            </td>
                        </tr>

                        <!-- Grand total -->
                        <tr class="border-t-2 font-bold">
                            <td class="bg-muted/40 px-3 py-2.5 text-xs uppercase tracking-wider text-muted-foreground">
                                Total
                            </td>
                            <td
                                v-for="pid in [game.player_one_id, game.player_two_id]"
                                :key="pid"
                                class="px-3 py-2.5 text-right text-base tabular-nums"
                                :class="[pc(pid).tint, pc(pid).text]"
                            >
                                {{ totals[pid].grand }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Legend / hint -->
            <div v-if="canScore" class="flex flex-wrap items-center justify-center gap-x-4 gap-y-2 text-xs text-muted-foreground">
                <span class="inline-flex items-center gap-1.5">
                    <span
                        class="inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-xs font-semibold"
                        :class="[myColor.softBorder, myColor.softBg, myColor.text]"
                    >
                        <Sparkles class="h-3 w-3" />
                        12
                    </span>
                    Best score
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <span
                        class="inline-flex items-center rounded-md border px-2 py-0.5 text-xs font-semibold"
                        :class="[myColor.softBorder, myColor.softBg, myColor.text]"
                    >
                        8
                    </span>
                    Tap to score
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <span class="inline-flex items-center rounded-md border border-muted-foreground/20 bg-muted/30 px-2 py-0.5 text-xs text-muted-foreground/70">
                        <span class="line-through">0</span>
                    </span>
                    Scratch
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <Circle class="h-2.5 w-2.5 fill-muted-foreground/40 text-muted-foreground/40" />
                    Locked
                </span>
            </div>
        </div>
    </AppLayout>
</template>
