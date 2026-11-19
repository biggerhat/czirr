<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Gamepad2, Plus, Trophy } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { api } from '@/lib/api';
import type { BreadcrumbItem } from '@/types';

interface Player {
    name: string;
    family_member_id?: number | null;
}
interface Game {
    id: number;
    title: string;
    kind: 'scorekeeper' | 'farkle';
    players: Player[];
    totals: number[];
    round_count: number;
    target_score: number | null;
    low_wins: boolean;
    status: 'active' | 'completed';
    winner_names: string | null;
}

const props = defineProps<{
    games: Game[];
    leaderboard: { name: string; wins: number }[];
    members: { id: number; name: string }[];
    canCreate: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Game night' }];

const showNew = ref(false);
const kind = ref<'scorekeeper' | 'farkle'>('farkle');
const title = ref('Farkle');
const picked = ref<number[]>([]);
const guests = ref('');
const target = ref<string>('10000');
const lowWins = ref(false);
const busy = ref(false);
const error = ref<string | null>(null);

function setKind(k: 'scorekeeper' | 'farkle') {
    kind.value = k;
    title.value = k === 'farkle' ? 'Farkle' : '';
    target.value = k === 'farkle' ? '10000' : '';
}

const players = computed(() => [
    ...props.members
        .filter((m) => picked.value.includes(m.id))
        .map((m) => ({ name: m.name, family_member_id: m.id })),
    ...guests.value
        .split(',')
        .map((n) => n.trim())
        .filter(Boolean)
        .map((name) => ({ name, family_member_id: null })),
]);

async function create() {
    if (busy.value) return;
    if (players.value.length < 2) {
        error.value = 'Pick at least two players.';
        return;
    }
    busy.value = true;
    error.value = null;
    const res = await api<{ id: number }>('POST', '/games', {
        title: title.value || 'Game',
        kind: kind.value,
        players: players.value,
        target_score: target.value ? Number(target.value) : null,
        low_wins: kind.value === 'scorekeeper' && lowWins.value,
    });
    busy.value = false;
    if (res.ok && res.data) router.visit(`/games/${res.data.id}`);
    else error.value = res.message ?? 'Could not start the game.';
}

const standings = (g: Game) =>
    g.players
        .map((p, i) => `${p.name} ${g.totals[i].toLocaleString()}`)
        .join(' · ');
</script>

<template>
    <Head title="Game night" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-3xl flex-col gap-4 p-4">
            <div class="flex items-center justify-between gap-2">
                <h1 class="text-xl font-semibold tracking-tight">Game night</h1>
                <Button v-if="canCreate" size="sm" @click="showNew = !showNew">
                    <Plus class="size-4" /> New game
                </Button>
            </div>

            <Card v-if="showNew">
                <CardHeader>
                    <CardTitle class="text-base">Start a game</CardTitle>
                </CardHeader>
                <CardContent class="flex flex-col gap-3">
                    <div class="flex gap-2">
                        <Button
                            size="sm"
                            :variant="kind === 'farkle' ? 'default' : 'outline'"
                            @click="setKind('farkle')"
                            >Farkle</Button
                        >
                        <Button
                            size="sm"
                            :variant="
                                kind === 'scorekeeper' ? 'default' : 'outline'
                            "
                            @click="setKind('scorekeeper')"
                            >Any game (scorekeeper)</Button
                        >
                    </div>
                    <Input
                        v-model="title"
                        aria-label="Game name"
                        placeholder="Game name (Rummy, Catan...)"
                        maxlength="80"
                    />
                    <fieldset class="flex flex-wrap gap-2">
                        <legend class="mb-1 text-sm font-medium">
                            Players
                        </legend>
                        <label
                            v-for="m in members"
                            :key="m.id"
                            class="flex items-center gap-1.5 rounded-md border px-2.5 py-1.5 text-sm"
                        >
                            <input
                                v-model="picked"
                                type="checkbox"
                                :value="m.id"
                            />
                            {{ m.name }}
                        </label>
                    </fieldset>
                    <Input
                        v-model="guests"
                        aria-label="Guest players"
                        placeholder="Guests, comma separated (optional)"
                    />
                    <div class="flex flex-wrap items-center gap-3">
                        <label class="flex items-center gap-2 text-sm">
                            Play to
                            <Input
                                v-model="target"
                                type="number"
                                min="1"
                                class="w-28"
                                placeholder="No limit"
                            />
                        </label>
                        <label
                            v-if="kind === 'scorekeeper'"
                            class="flex items-center gap-1.5 text-sm"
                        >
                            <input v-model="lowWins" type="checkbox" />
                            Lowest score wins
                        </label>
                    </div>
                    <p
                        v-if="error"
                        class="text-sm text-red-700 dark:text-red-400"
                    >
                        {{ error }}
                    </p>
                    <Button :disabled="busy" @click="create">Start</Button>
                </CardContent>
            </Card>

            <Card v-if="leaderboard.length">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-base">
                        <Trophy class="size-4" /> Game night wins
                    </CardTitle>
                </CardHeader>
                <CardContent class="flex flex-wrap gap-2">
                    <Badge
                        v-for="l in leaderboard"
                        :key="l.name"
                        variant="secondary"
                        >{{ l.name }} · {{ l.wins }}</Badge
                    >
                </CardContent>
            </Card>

            <p
                v-if="!games.length"
                class="py-8 text-center text-sm text-muted-foreground"
            >
                No games yet. Start one with “New game”.
            </p>

            <Link
                v-for="g in games"
                :key="g.id"
                :href="`/games/${g.id}`"
                class="rounded-lg border p-3 transition-colors hover:bg-muted/50"
            >
                <div class="flex items-center justify-between gap-2">
                    <span class="flex items-center gap-2 font-medium">
                        <Gamepad2 class="size-4" /> {{ g.title }}
                    </span>
                    <Badge
                        :variant="
                            g.status === 'active' ? 'default' : 'secondary'
                        "
                        >{{
                            g.status === 'active'
                                ? 'In progress'
                                : `Won by ${g.winner_names ?? 'nobody'}`
                        }}</Badge
                    >
                </div>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ standings(g) }}
                </p>
            </Link>
        </div>
    </AppLayout>
</template>
