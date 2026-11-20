<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { Trash2 } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { api } from '@/lib/api';
import type { BreadcrumbItem } from '@/types';

const props = defineProps<{
    game: {
        id: number;
        kind: 'tictactoe' | 'connect4';
        board: (number | null)[];
        cols: number;
        status: 'active' | 'won' | 'draw';
        player_one: { id: number; name: string };
        player_two: { id: number; name: string };
        current_turn_user_id: number | null;
        winner_id: number | null;
    };
    currentUserId: number;
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Game night', href: '/games' },
    { title: 'Duels', href: '/games/duels' },
    { title: props.game.kind === 'connect4' ? 'Connect Four' : 'Tic-tac-toe' },
]);

const page = usePage();
const error = ref<string | null>(null);
const myTurn = computed(
    () =>
        props.game.status === 'active' &&
        props.game.current_turn_user_id === props.currentUserId,
);

const status = computed(() => {
    const g = props.game;
    if (g.status === 'draw') return "It's a draw";
    if (g.status === 'won')
        return g.winner_id === props.currentUserId
            ? 'You won! 🎉'
            : `${g.winner_id === g.player_one.id ? g.player_one.name : g.player_two.name} won`;
    return myTurn.value ? 'Your turn' : 'Waiting for the other player…';
});

const markClass = (m: number | null) =>
    m === 1
        ? 'bg-red-500 text-white'
        : m === 2
          ? 'bg-yellow-400 text-black'
          : 'bg-muted';
const mark = (m: number | null) =>
    props.game.kind === 'tictactoe' ? (m === 1 ? 'X' : m === 2 ? 'O' : '') : '';

async function play(index: number) {
    if (!myTurn.value) return;
    error.value = null;
    const move =
        props.game.kind === 'connect4' ? index % props.game.cols : index;
    const res = await api('POST', `/games/duels/${props.game.id}/move`, {
        move,
    });
    if (res.ok) router.reload({ only: ['game'] });
    else error.value = res.message ?? 'That move is not allowed.';
}

async function remove() {
    if (!window.confirm('Delete this game?')) return;
    if ((await api('DELETE', `/games/duels/${props.game.id}`)).ok)
        router.visit('/games/duels');
}

let timer: ReturnType<typeof setInterval> | null = null;
onMounted(() => {
    timer = setInterval(() => {
        if (
            !page.props.realtime &&
            !myTurn.value &&
            props.game.status === 'active' &&
            !document.hidden
        )
            router.reload({ only: ['game'] });
    }, 4000);
});
onBeforeUnmount(() => timer && clearInterval(timer));
</script>

<template>
    <Head :title="game.kind === 'connect4' ? 'Connect Four' : 'Tic-tac-toe'" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-md flex-col gap-4 p-4">
            <h1 class="text-xl font-semibold tracking-tight">
                {{ game.player_one.name }}
                <span class="text-red-600 dark:text-red-400">●</span> vs
                {{ game.player_two.name }}
                <span class="text-yellow-600 dark:text-yellow-400">●</span>
            </h1>
            <p class="text-sm font-medium" role="status">{{ status }}</p>
            <div
                class="grid gap-1.5 rounded-xl bg-blue-700 p-2"
                :class="game.kind === 'tictactoe' ? 'bg-slate-700' : ''"
                :style="{
                    gridTemplateColumns: `repeat(${game.cols}, minmax(0, 1fr))`,
                }"
            >
                <button
                    v-for="(m, i) in game.board"
                    :key="i"
                    type="button"
                    class="flex aspect-square items-center justify-center text-2xl font-bold disabled:cursor-default"
                    :class="[
                        markClass(m),
                        game.kind === 'connect4'
                            ? 'rounded-full'
                            : 'rounded-md',
                    ]"
                    :disabled="!myTurn"
                    :aria-label="
                        game.kind === 'connect4'
                            ? `Drop in column ${(i % game.cols) + 1}`
                            : `Square ${i + 1}${m ? ' taken' : ''}`
                    "
                    @click="play(i)"
                >
                    {{ mark(m) }}
                </button>
            </div>
            <p v-if="error" class="text-sm text-red-700 dark:text-red-400">
                {{ error }}
            </p>
            <Button
                variant="ghost"
                size="sm"
                class="self-start text-muted-foreground hover:text-destructive"
                @click="remove"
                ><Trash2 class="size-4" /> Delete game</Button
            >
        </div>
    </AppLayout>
</template>
