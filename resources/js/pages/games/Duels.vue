<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Brain, Grid3x3, Swords } from 'lucide-vue-next';
import { ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { api } from '@/lib/api';
import type { BreadcrumbItem } from '@/types';

interface Duel {
    id: number;
    kind: 'tictactoe' | 'connect4';
    status: 'active' | 'won' | 'draw';
    player_one: { id: number; name: string };
    player_two: { id: number; name: string };
    current_turn_user_id: number | null;
    winner_id: number | null;
}

const props = defineProps<{
    games: Duel[];
    opponents: { id: number; name: string }[];
    currentUserId: number;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Game night', href: '/games' },
    { title: 'Duels' },
];

const kind = ref<'tictactoe' | 'connect4'>('connect4');
const opponent = ref<number | null>(props.opponents[0]?.id ?? null);
const error = ref<string | null>(null);

const label = (k: string) =>
    k === 'connect4' ? 'Connect Four' : 'Tic-tac-toe';
const other = (g: Duel) =>
    g.player_one.id === props.currentUserId ? g.player_two : g.player_one;
const state = (g: Duel) => {
    if (g.status === 'draw') return 'Draw';
    if (g.status === 'won')
        return g.winner_id === props.currentUserId ? 'You won' : 'You lost';
    return g.current_turn_user_id === props.currentUserId
        ? 'Your turn'
        : 'Their turn';
};

async function start() {
    if (!opponent.value) return;
    const res = await api<{ id: number }>('POST', '/games/duels', {
        kind: kind.value,
        opponent_id: opponent.value,
    });
    if (res.ok && res.data) router.visit(`/games/duels/${res.data.id}`);
    else error.value = res.message ?? 'Could not start the game.';
}
</script>

<template>
    <Head title="Duels" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-3xl flex-col gap-4 p-4">
            <h1 class="text-xl font-semibold tracking-tight">Duels</h1>
            <div class="flex flex-wrap gap-2 text-sm">
                <Link
                    href="/games"
                    class="rounded-md border px-3 py-1.5 hover:bg-muted"
                    ><Grid3x3 class="mr-1 inline size-4" />Scorekeeper</Link
                >
                <Link
                    href="/games/memory"
                    class="rounded-md border px-3 py-1.5 hover:bg-muted"
                    ><Brain class="mr-1 inline size-4" />Memory match</Link
                >
            </div>

            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-base"
                        ><Swords class="size-4" /> Challenge someone</CardTitle
                    >
                </CardHeader>
                <CardContent class="flex flex-col gap-3">
                    <div class="flex gap-2">
                        <Button
                            size="sm"
                            :variant="
                                kind === 'connect4' ? 'default' : 'outline'
                            "
                            @click="kind = 'connect4'"
                            >Connect Four</Button
                        >
                        <Button
                            size="sm"
                            :variant="
                                kind === 'tictactoe' ? 'default' : 'outline'
                            "
                            @click="kind = 'tictactoe'"
                            >Tic-tac-toe</Button
                        >
                    </div>
                    <p
                        v-if="!opponents.length"
                        class="text-sm text-muted-foreground"
                    >
                        Link another family member to play against them.
                    </p>
                    <div v-else class="flex gap-2">
                        <select
                            v-model="opponent"
                            aria-label="Opponent"
                            class="h-9 flex-1 rounded-md border bg-background px-2 text-sm"
                        >
                            <option
                                v-for="o in opponents"
                                :key="o.id"
                                :value="o.id"
                            >
                                {{ o.name }}
                            </option>
                        </select>
                        <Button @click="start">Start</Button>
                    </div>
                    <p
                        v-if="error"
                        class="text-sm text-red-700 dark:text-red-400"
                    >
                        {{ error }}
                    </p>
                </CardContent>
            </Card>

            <Link
                v-for="g in games"
                :key="g.id"
                :href="`/games/duels/${g.id}`"
                class="flex items-center justify-between rounded-lg border p-3 hover:bg-muted/50"
            >
                <span class="font-medium"
                    >{{ label(g.kind) }} vs {{ other(g).name }}</span
                >
                <Badge
                    :variant="g.status === 'active' ? 'default' : 'secondary'"
                    >{{ state(g) }}</Badge
                >
            </Link>
        </div>
    </AppLayout>
</template>
