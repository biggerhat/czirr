<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Flag, Trash2, Undo2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { api } from '@/lib/api';
import type { BreadcrumbItem } from '@/types';

interface Game {
    id: number;
    title: string;
    kind: 'scorekeeper' | 'farkle';
    players: { name: string }[];
    totals: number[];
    rounds: number[][];
    target_score: number | null;
    low_wins: boolean;
    status: 'active' | 'completed';
    winner_names: string | null;
}

const props = defineProps<{ game: Game; canEdit: boolean }>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Game night', href: '/games' },
    { title: props.game.title },
]);

const entry = ref<string[]>(props.game.players.map(() => ''));
const busy = ref(false);
const error = ref<string | null>(null);

const lead = computed(() => {
    const t = props.game.totals;
    return props.game.low_wins ? Math.min(...t) : Math.max(...t);
});
const active = computed(() => props.game.status === 'active');
const reload = () => router.reload();

async function submitRound() {
    if (busy.value) return;
    busy.value = true;
    error.value = null;
    const res = await api('POST', `/games/${props.game.id}/rounds`, {
        scores: entry.value.map((v) => (v === '' ? 0 : Number(v))),
    });
    busy.value = false;
    if (res.ok) {
        entry.value = props.game.players.map(() => '');
        reload();
    } else error.value = res.message ?? 'Could not save the round.';
}

async function undo() {
    if ((await api('DELETE', `/games/${props.game.id}/rounds`)).ok) reload();
}
async function finish() {
    if (!window.confirm('End the game and crown the leader?')) return;
    if ((await api('POST', `/games/${props.game.id}/finish`)).ok) reload();
}
async function remove() {
    if (!window.confirm('Delete this game?')) return;
    if ((await api('DELETE', `/games/${props.game.id}`)).ok)
        router.visit('/games');
}
</script>

<template>
    <Head :title="game.title" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-3xl flex-col gap-4 p-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h1 class="text-xl font-semibold tracking-tight">
                        {{ game.title }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        <template v-if="game.target_score"
                            >{{ game.low_wins ? 'Game ends at' : 'First to' }}
                            {{ game.target_score.toLocaleString() }} ·
                        </template>
                        {{ game.rounds.length }} round{{
                            game.rounds.length === 1 ? '' : 's'
                        }}
                    </p>
                </div>
                <Badge v-if="!active" class="text-sm"
                    >Won by {{ game.winner_names ?? 'nobody' }}</Badge
                >
            </div>

            <div
                class="grid gap-2"
                :style="{
                    gridTemplateColumns: `repeat(${Math.min(game.players.length, 4)}, minmax(0, 1fr))`,
                }"
            >
                <Card
                    v-for="(p, i) in game.players"
                    :key="i"
                    :class="
                        game.totals[i] === lead && game.rounds.length
                            ? 'border-primary'
                            : ''
                    "
                >
                    <CardContent class="p-3 text-center">
                        <p class="truncate text-sm font-medium">{{ p.name }}</p>
                        <p class="text-2xl font-bold tabular-nums">
                            {{ game.totals[i].toLocaleString() }}
                        </p>
                        <div
                            v-if="game.target_score && !game.low_wins"
                            class="mt-1 h-1.5 overflow-hidden rounded bg-muted"
                            aria-hidden="true"
                        >
                            <div
                                class="h-full bg-primary"
                                :style="{
                                    width: `${Math.min(100, (Math.max(game.totals[i], 0) / game.target_score) * 100)}%`,
                                }"
                            />
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Card v-if="active && canEdit">
                <CardContent class="flex flex-col gap-3 p-4">
                    <p class="text-sm font-medium">
                        Round {{ game.rounds.length + 1 }}
                    </p>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <div
                            v-for="(p, i) in game.players"
                            :key="i"
                            class="flex items-center gap-2"
                        >
                            <span class="w-24 truncate text-sm">{{
                                p.name
                            }}</span>
                            <Input
                                v-model="entry[i]"
                                type="number"
                                inputmode="numeric"
                                :aria-label="`${p.name} score this round`"
                                placeholder="0"
                            />
                            <Button
                                v-if="game.kind === 'farkle'"
                                type="button"
                                size="sm"
                                variant="outline"
                                :aria-label="`${p.name} farkled`"
                                @click="entry[i] = '0'"
                                >Farkle</Button
                            >
                        </div>
                    </div>
                    <p
                        v-if="error"
                        class="text-sm text-red-700 dark:text-red-400"
                    >
                        {{ error }}
                    </p>
                    <Button :disabled="busy" @click="submitRound"
                        >Add round</Button
                    >
                </CardContent>
            </Card>

            <div
                v-if="game.rounds.length"
                class="overflow-x-auto rounded-lg border"
            >
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-muted/50 text-left">
                            <th class="p-2 font-medium">Round</th>
                            <th
                                v-for="(p, i) in game.players"
                                :key="i"
                                class="p-2 text-right font-medium"
                            >
                                {{ p.name }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(r, ri) in game.rounds"
                            :key="ri"
                            class="border-b last:border-0"
                        >
                            <td class="p-2 text-muted-foreground">
                                {{ ri + 1 }}
                            </td>
                            <td
                                v-for="(s, si) in r"
                                :key="si"
                                class="p-2 text-right tabular-nums"
                            >
                                {{ s.toLocaleString() }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="canEdit" class="flex flex-wrap gap-2">
                <Button
                    v-if="game.rounds.length"
                    variant="outline"
                    size="sm"
                    @click="undo"
                    ><Undo2 class="size-4" /> Undo last round</Button
                >
                <Button
                    v-if="active"
                    variant="outline"
                    size="sm"
                    @click="finish"
                    ><Flag class="size-4" /> End game</Button
                >
                <Button
                    variant="ghost"
                    size="sm"
                    class="text-muted-foreground hover:text-destructive"
                    aria-label="Delete game"
                    @click="remove"
                    ><Trash2 class="size-4" /> Delete</Button
                >
            </div>
        </div>
    </AppLayout>
</template>
