<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Dices, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import { playerColorClasses } from '@/lib/yahtzee';
import type { BreadcrumbItem } from '@/types';
import type { EventColor } from '@/types/calendar';
import type { PlayerSummary, YahtzeeGame } from '@/types/yahtzee';

const props = defineProps<{
    games: YahtzeeGame[];
    opponents: PlayerSummary[];
    currentUserId: number;
    playerColors: Record<number, EventColor>;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Yahtzee' }];

const opponentId = ref<string>('');
const isStarting = ref(false);
const deletingId = ref<number | null>(null);

function pc(pid: number) {
    return playerColorClasses(props.playerColors[pid]);
}

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

async function startGame() {
    if (!opponentId.value) return;
    isStarting.value = true;
    const res = await fetch('/yahtzee', {
        method: 'POST',
        headers: xsrfHeaders(),
        body: JSON.stringify({ opponent_id: Number(opponentId.value) }),
    });
    isStarting.value = false;
    if (res.ok) {
        const data = await res.json();
        router.visit(`/yahtzee/${data.id}`);
    }
}

async function deleteGame() {
    if (!deletingId.value) return;
    await fetch(`/yahtzee/${deletingId.value}`, {
        method: 'DELETE',
        headers: xsrfHeaders(),
    });
    deletingId.value = null;
    router.reload();
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="flex items-center justify-between gap-2">
                <h2 class="flex items-center gap-2 text-lg font-semibold">
                    <Dices class="h-5 w-5" />
                    Yahtzee
                </h2>
            </div>

            <div class="rounded-lg border p-4">
                <h3 class="mb-3 text-sm font-medium">Start a new game</h3>
                <div v-if="opponents.length === 0" class="text-sm text-muted-foreground">
                    Invite a family member with their own login to play.
                </div>
                <div v-else class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <Select v-model="opponentId">
                        <SelectTrigger class="w-full sm:w-64">
                            <SelectValue placeholder="Choose opponent" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="opp in opponents"
                                :key="opp.id"
                                :value="String(opp.id)"
                            >
                                <span class="inline-flex items-center gap-2">
                                    <span class="h-2 w-2 rounded-full" :class="pc(opp.id).dot" />
                                    {{ opp.name }}
                                </span>
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <Button :disabled="!opponentId || isStarting" @click="startGame">
                        {{ isStarting ? 'Starting…' : 'Start game' }}
                    </Button>
                </div>
            </div>

            <div class="rounded-lg border">
                <div v-if="games.length === 0" class="p-8 text-center text-sm text-muted-foreground">
                    No games yet. Start one above!
                </div>
                <div v-else class="divide-y">
                    <div
                        v-for="game in games"
                        :key="game.id"
                        class="flex items-center gap-3 px-4 py-3"
                    >
                        <Link
                            :href="`/yahtzee/${game.id}`"
                            class="flex flex-1 items-center gap-3 hover:opacity-80"
                        >
                            <div class="flex-1 min-w-0">
                                <div class="flex flex-wrap items-center gap-1.5 text-sm font-medium">
                                    <span class="inline-flex items-center gap-1.5">
                                        <span class="h-2 w-2 rounded-full" :class="pc(game.player_one_id).dot" />
                                        <span :class="pc(game.player_one_id).text">{{ game.player_one.name }}</span>
                                    </span>
                                    <span class="text-xs text-muted-foreground">vs</span>
                                    <span class="inline-flex items-center gap-1.5">
                                        <span class="h-2 w-2 rounded-full" :class="pc(game.player_two_id).dot" />
                                        <span :class="pc(game.player_two_id).text">{{ game.player_two.name }}</span>
                                    </span>
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    <template v-if="game.status === 'completed'">
                                        <template v-if="game.winner">
                                            <span :class="pc(game.winner.id).text" class="font-medium">{{ game.winner.name }}</span>
                                            won
                                        </template>
                                        <template v-else>Tie!</template>
                                    </template>
                                    <template v-else-if="game.current_turn_user_id === currentUserId">
                                        Your turn
                                    </template>
                                    <template v-else>
                                        Waiting on
                                        <span :class="pc(game.current_turn_user_id).text">
                                            {{ game.current_turn_user.name }}
                                        </span>
                                    </template>
                                </div>
                            </div>
                            <span
                                v-if="game.status === 'completed'"
                                class="rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground"
                            >
                                Done
                            </span>
                            <span
                                v-else-if="game.current_turn_user_id === currentUserId"
                                class="rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="[pc(currentUserId).chipBg, pc(currentUserId).text]"
                            >
                                Your move
                            </span>
                            <span
                                v-else
                                class="rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground"
                            >
                                Active
                            </span>
                        </Link>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="h-8 w-8 text-destructive"
                            @click="deletingId = game.id"
                        >
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </div>
                </div>
            </div>

            <Dialog :open="deletingId !== null" @update:open="$event || (deletingId = null)">
                <DialogContent class="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Delete game?</DialogTitle>
                        <DialogDescription>
                            This will permanently remove the game for both players.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter class="gap-2">
                        <Button variant="outline" @click="deletingId = null">Cancel</Button>
                        <Button variant="destructive" @click="deleteGame">Delete</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>
