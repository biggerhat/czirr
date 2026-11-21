<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { RotateCcw } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref } from 'vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Game night', href: '/games' },
    { title: 'Memory match' },
];

const EMOJI = [
    '🐶',
    '🐱',
    '🦊',
    '🐼',
    '🐸',
    '🦄',
    '🐙',
    '🦋',
    '🍕',
    '🚀',
    '⚽',
    '🌈',
];
const sizes = { Easy: 6, Medium: 8, Hard: 12 } as const;

interface Card {
    id: number;
    face: string;
    matched: boolean;
}

const level = ref<keyof typeof sizes>('Easy');
const cards = ref<Card[]>([]);
const open = ref<number[]>([]);
const moves = ref(0);
const locked = ref(false);
const bestKey = computed(() => `memory-best-${level.value}`);
const best = ref<number | null>(null);
let timer: ReturnType<typeof setTimeout> | null = null;

const won = computed(
    () => cards.value.length > 0 && cards.value.every((c) => c.matched),
);

function readBest() {
    try {
        const v = localStorage.getItem(bestKey.value);
        best.value = v ? Number(v) : null;
    } catch {
        best.value = null;
    }
}

function start() {
    const faces = EMOJI.slice(0, sizes[level.value]);
    const deck = [...faces, ...faces]
        .map((face) => ({ face, sort: Math.random() }))
        .sort((a, b) => a.sort - b.sort)
        .map((c, id) => ({ id, face: c.face, matched: false }));
    cards.value = deck;
    open.value = [];
    moves.value = 0;
    locked.value = false;
    readBest();
}

function flip(i: number) {
    const c = cards.value[i];
    if (locked.value || c.matched || open.value.includes(i)) return;
    open.value.push(i);
    if (open.value.length < 2) return;

    moves.value++;
    const [a, b] = open.value;
    if (cards.value[a].face === cards.value[b].face) {
        cards.value[a].matched = cards.value[b].matched = true;
        open.value = [];
        if (won.value && (best.value === null || moves.value < best.value)) {
            best.value = moves.value;
            try {
                localStorage.setItem(bestKey.value, String(moves.value));
            } catch {
                /* storage unavailable */
            }
        }
    } else {
        locked.value = true;
        timer = setTimeout(() => {
            open.value = [];
            locked.value = false;
        }, 900);
    }
}

function setLevel(l: keyof typeof sizes) {
    level.value = l;
    start();
}

start();
onBeforeUnmount(() => timer && clearTimeout(timer));
</script>

<template>
    <Head title="Memory match" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-md flex-col gap-4 p-4">
            <h1 class="text-xl font-semibold tracking-tight">Memory match</h1>
            <div class="flex flex-wrap items-center gap-2">
                <Button
                    v-for="(_, l) in sizes"
                    :key="l"
                    size="sm"
                    :variant="level === l ? 'default' : 'outline'"
                    @click="setLevel(l)"
                    >{{ l }}</Button
                >
                <Button size="sm" variant="ghost" @click="start"
                    ><RotateCcw class="size-4" /> Restart</Button
                >
            </div>
            <p class="text-sm text-muted-foreground" role="status">
                <template v-if="won"
                    >You did it in {{ moves }} moves! 🎉</template
                >
                <template v-else>Moves: {{ moves }}</template>
                <template v-if="best !== null"> · Best: {{ best }}</template>
            </p>
            <div class="grid grid-cols-4 gap-2">
                <button
                    v-for="(c, i) in cards"
                    :key="c.id"
                    type="button"
                    class="flex aspect-square items-center justify-center rounded-lg border text-3xl transition-colors"
                    :class="
                        c.matched || open.includes(i)
                            ? 'bg-muted'
                            : 'bg-primary text-primary-foreground'
                    "
                    :aria-label="
                        c.matched || open.includes(i)
                            ? `Card ${i + 1}: ${c.face}`
                            : `Card ${i + 1}, face down`
                    "
                    @click="flip(i)"
                >
                    <span v-if="c.matched || open.includes(i)">{{
                        c.face
                    }}</span>
                    <span v-else aria-hidden="true">?</span>
                </button>
            </div>
        </div>
    </AppLayout>
</template>
