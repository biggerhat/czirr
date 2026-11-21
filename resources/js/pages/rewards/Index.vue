<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Coins, Gift, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import { api } from '@/lib/api';
import type { BreadcrumbItem } from '@/types';

interface Reward {
    id: number;
    name: string;
    description: string | null;
    cost: number;
    is_active: boolean;
}
interface MemberBalance {
    id: number;
    name: string;
    color: string | null;
    balance: number;
}
interface Redemption {
    id: number;
    reward_name: string;
    cost: number;
    status: 'pending' | 'approved' | 'denied';
    family_member: { name: string; nickname: string | null } | null;
}

const props = defineProps<{
    rewards: Reward[];
    members: MemberBalance[];
    myMemberId: number | null;
    pending: Redemption[];
    history: Redemption[];
    can: { manage: boolean };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Rewards' }];

const selectedMember = ref<number | null>(
    props.myMemberId ?? props.members[0]?.id ?? null,
);
const message = ref<string | null>(null);

const balanceOf = (id: number | null) =>
    props.members.find((m) => m.id === id)?.balance ?? 0;
const who = (r: Redemption) =>
    r.family_member?.nickname ?? r.family_member?.name ?? '';

async function redeem(reward: Reward) {
    message.value = null;
    const res = await api('POST', `/rewards/${reward.id}/redeem`, {
        family_member_id: selectedMember.value,
    });
    if (!res.ok) message.value = res.message ?? 'Could not redeem.';
    else {
        message.value = `Requested "${reward.name}" — waiting for a parent to approve.`;
        router.reload();
    }
}

async function resolve(r: Redemption, status: 'approved' | 'denied') {
    await api('PATCH', `/reward-redemptions/${r.id}`, { status });
    router.reload();
}

// Reward editor
const editing = ref<Reward | null>(null);
const open = ref(false);
const form = ref({ name: '', description: '', cost: 10, is_active: true });

function startEdit(reward: Reward | null) {
    editing.value = reward;
    form.value = reward
        ? {
              name: reward.name,
              description: reward.description ?? '',
              cost: reward.cost,
              is_active: reward.is_active,
          }
        : { name: '', description: '', cost: 10, is_active: true };
    open.value = true;
}

async function save() {
    const res = editing.value
        ? await api('PUT', `/rewards/${editing.value.id}`, form.value)
        : await api('POST', '/rewards', form.value);
    if (res.ok) {
        open.value = false;
        router.reload();
    }
}

async function remove(reward: Reward) {
    if (!confirm(`Delete "${reward.name}"?`)) return;
    await api('DELETE', `/rewards/${reward.id}`);
    router.reload();
}
</script>

<template>
    <Head title="Rewards" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4">
            <div class="flex items-center justify-between">
                <h1 class="flex items-center gap-2 text-xl font-semibold tracking-tight">
                    <Gift class="h-6 w-6" /> Rewards
                </h1>
                <Button v-if="can.manage" @click="startEdit(null)">
                    <Plus class="mr-1 h-4 w-4" /> New reward
                </Button>
            </div>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <button
                    v-for="m in members"
                    :key="m.id"
                    type="button"
                    class="rounded-xl border-2 p-3 text-left transition"
                    :class="
                        m.id === selectedMember
                            ? 'border-primary bg-primary/5'
                            : 'border-border'
                    "
                    @click="selectedMember = m.id"
                >
                    <div class="truncate text-sm text-muted-foreground">
                        {{ m.name }}
                    </div>
                    <div class="flex items-center gap-1 text-2xl font-bold">
                        <Coins class="h-5 w-5 text-emerald-500" />
                        {{ m.balance }}
                    </div>
                </button>
            </div>

            <p v-if="message" class="rounded-md bg-muted p-3 text-sm">
                {{ message }}
            </p>

            <div
                v-if="rewards.length === 0"
                class="py-10 text-center text-muted-foreground"
            >
                No rewards yet.
                <template v-if="can.manage">
                    Add things like "30 min screen time" or "Pick dinner".
                </template>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <Card
                    v-for="r in rewards"
                    :key="r.id"
                    :class="{ 'opacity-60': !r.is_active }"
                >
                    <CardHeader class="pb-2">
                        <CardTitle
                            class="flex items-start justify-between gap-2 text-lg"
                        >
                            <span>{{ r.name }}</span>
                            <Badge>{{ r.cost }} pts</Badge>
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <p
                            v-if="r.description"
                            class="text-sm text-muted-foreground"
                        >
                            {{ r.description }}
                        </p>
                        <div class="flex items-center gap-2">
                            <Button
                                size="sm"
                                :disabled="
                                    !r.is_active ||
                                    selectedMember === null ||
                                    balanceOf(selectedMember) < r.cost
                                "
                                @click="redeem(r)"
                            >
                                Redeem
                            </Button>
                            <template v-if="can.manage">
                                <Badge v-if="!r.is_active" variant="outline"
                                    >Hidden</Badge
                                >
                                <Button aria-label="Edit"
                                    size="icon"
                                    variant="ghost"
                                    @click="startEdit(r)"
                                >
                                    <Pencil class="h-4 w-4" />
                                </Button>
                                <Button aria-label="Delete"
                                    size="icon"
                                    variant="ghost"
                                    @click="remove(r)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </template>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Card v-if="pending.length">
                <CardHeader>
                    <CardTitle class="text-lg">Waiting for approval</CardTitle>
                </CardHeader>
                <CardContent class="space-y-2">
                    <div
                        v-for="r in pending"
                        :key="r.id"
                        class="flex items-center justify-between gap-2 rounded-md border p-3"
                    >
                        <span>
                            <strong>{{ who(r) }}</strong> — {{ r.reward_name }}
                            <span class="text-muted-foreground"
                                >({{ r.cost }} pts)</span
                            >
                        </span>
                        <span v-if="can.manage" class="flex gap-2">
                            <Button size="sm" @click="resolve(r, 'approved')"
                                >Approve</Button
                            >
                            <Button
                                size="sm"
                                variant="outline"
                                @click="resolve(r, 'denied')"
                            >
                                Deny
                            </Button>
                        </span>
                        <Badge v-else variant="secondary">Pending</Badge>
                    </div>
                </CardContent>
            </Card>

            <Card v-if="history.length">
                <CardHeader>
                    <CardTitle class="text-lg">History</CardTitle>
                </CardHeader>
                <CardContent class="space-y-1 text-sm">
                    <div
                        v-for="r in history"
                        :key="r.id"
                        class="flex items-center justify-between"
                    >
                        <span
                            >{{ who(r) }} — {{ r.reward_name }} ({{
                                r.cost
                            }}
                            pts)</span
                        >
                        <Badge
                            :variant="
                                r.status === 'approved' ? 'default' : 'outline'
                            "
                        >
                            {{ r.status }}
                        </Badge>
                    </div>
                </CardContent>
            </Card>
        </div>

        <Dialog v-model:open="open">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{
                        editing ? 'Edit reward' : 'New reward'
                    }}</DialogTitle>
                </DialogHeader>
                <div class="grid gap-4">
                    <div class="grid gap-2">
                        <Label for="r-name">Name</Label>
                        <Input id="r-name" v-model="form.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="r-desc">Description</Label>
                        <Textarea id="r-desc" v-model="form.description" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="r-cost">Cost (points)</Label>
                        <Input
                            id="r-cost"
                            v-model.number="form.cost"
                            type="number"
                            min="1"
                        />
                    </div>
                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="form.is_active" type="checkbox" />
                        Available to redeem
                    </label>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="open = false"
                        >Cancel</Button
                    >
                    <Button
                        :disabled="!form.name || form.cost < 1"
                        @click="save"
                        >Save</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
