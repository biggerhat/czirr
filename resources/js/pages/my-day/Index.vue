<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Check, Coins, Flame, Gift, Star } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { api } from '@/lib/api';
import type { BreadcrumbItem } from '@/types';

interface Member {
    id: number;
    name: string;
    nickname: string | null;
    color: string | null;
}
interface DayChore {
    assignment_id: number;
    name: string;
    description: string | null;
    points: number;
    completed: boolean;
    pending?: boolean;
}

const props = defineProps<{
    member: Member | null;
    members: Member[];
    chores: DayChore[];
    stats: { streak: number; weekly_total: number; balance: number } | null;
    date: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'My Day' }];

const chores = ref<DayChore[]>(props.chores);
watch(
    () => props.chores,
    (v) => (chores.value = v),
);

const pending = ref<number | null>(null);

async function toggle(chore: DayChore) {
    if (pending.value !== null) return;
    pending.value = chore.assignment_id;
    const before = { completed: chore.completed, pending: chore.pending };
    chore.completed = !chore.completed; // optimistic
    if (!chore.completed) chore.pending = false;

    const res = await api('POST', '/chore-completions/toggle', {
        chore_assignment_id: chore.assignment_id,
        date: props.date,
    });
    if (!res.ok) {
        chore.completed = before.completed;
        chore.pending = before.pending;
        if (res.message) window.alert(res.message);
    } else {
        router.reload({ only: ['chores', 'stats'] });
    }
    pending.value = null;
}

function switchMember(id: number) {
    router.get('/my-day', { member: id }, { preserveScroll: true });
}

const name = (m: Member) => m.nickname ?? m.name;

const remaining = () => chores.value.filter((c) => !c.completed).length;
const waiting = () => chores.value.filter((c) => c.pending).length;
</script>

<template>
    <Head title="My Day" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4">
            <div v-if="!member" class="py-16 text-center text-muted-foreground">
                Your account isn't linked to a family member yet. Ask a parent
                to link you on the Family page.
            </div>

            <template v-else>
                <div v-if="members.length > 1" class="flex flex-wrap gap-2">
                    <Button
                        v-for="m in members"
                        :key="m.id"
                        size="sm"
                        :variant="m.id === member.id ? 'default' : 'outline'"
                        @click="switchMember(m.id)"
                    >
                        {{ name(m) }}
                    </Button>
                </div>

                <div>
                    <h1 class="text-3xl font-bold">
                        Hi {{ name(member) }}! 👋
                    </h1>
                    <p class="text-muted-foreground">
                        <template v-if="chores.length === 0">
                            No chores today — enjoy your day!
                        </template>
                        <template
                            v-else-if="remaining() === 0 && waiting() > 0"
                        >
                            All done — {{ waiting() }} waiting for a parent to
                            approve. 🙌
                        </template>
                        <template v-else-if="remaining() === 0">
                            All done for today. Great job! 🎉
                        </template>
                        <template v-else>
                            {{ remaining() }} chore{{
                                remaining() === 1 ? '' : 's'
                            }}
                            left today
                        </template>
                    </p>
                </div>

                <div v-if="stats" class="grid grid-cols-3 gap-3">
                    <Card>
                        <CardContent class="p-4 text-center">
                            <Flame
                                class="mx-auto mb-1 h-6 w-6 text-orange-500"
                            />
                            <div class="text-2xl font-bold">
                                {{ stats.streak }}
                            </div>
                            <div class="text-xs text-muted-foreground">
                                day streak
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent class="p-4 text-center">
                            <Star
                                class="mx-auto mb-1 h-6 w-6 text-yellow-500"
                            />
                            <div class="text-2xl font-bold">
                                {{ stats.weekly_total }}
                            </div>
                            <div class="text-xs text-muted-foreground">
                                this week
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent class="p-4 text-center">
                            <Coins
                                class="mx-auto mb-1 h-6 w-6 text-emerald-500"
                            />
                            <div class="text-2xl font-bold">
                                {{ stats.balance }}
                            </div>
                            <div class="text-xs text-muted-foreground">
                                to spend
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div class="flex flex-col gap-3">
                    <button
                        v-for="chore in chores"
                        :key="chore.assignment_id"
                        type="button"
                        class="flex w-full items-center gap-4 rounded-xl border-2 p-5 text-left transition active:scale-[0.98]"
                        :class="
                            chore.completed
                                ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-950/30'
                                : 'border-border bg-card hover:border-primary'
                        "
                        :aria-pressed="chore.completed"
                        @click="toggle(chore)"
                    >
                        <span
                            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full border-2"
                            :class="
                                chore.completed
                                    ? 'border-emerald-500 bg-emerald-500 text-white'
                                    : 'border-muted-foreground/40'
                            "
                        >
                            <Check v-if="chore.completed" class="h-7 w-7" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span
                                class="block text-lg font-semibold"
                                :class="{
                                    'text-muted-foreground line-through':
                                        chore.completed,
                                }"
                            >
                                {{ chore.name }}
                            </span>
                            <span
                                v-if="chore.description"
                                class="block truncate text-sm text-muted-foreground"
                            >
                                {{ chore.description }}
                            </span>
                        </span>
                        <Badge
                            v-if="chore.pending"
                            variant="outline"
                            class="text-amber-700 dark:text-amber-400"
                            >awaiting approval</Badge
                        >
                        <Badge v-else variant="secondary"
                            >+{{ chore.points }}</Badge
                        >
                    </button>
                </div>

                <Button as-child variant="outline" size="lg">
                    <a href="/rewards"
                        ><Gift class="mr-2 h-5 w-5" /> Rewards shop</a
                    >
                </Button>
            </template>
        </div>
    </AppLayout>
</template>
