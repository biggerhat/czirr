<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { PiggyBank, Trash2 } from 'lucide-vue-next';
import { reactive, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { api } from '@/lib/api';
import type { BreadcrumbItem } from '@/types';

interface Entry {
    id: number;
    amount: number;
    note: string | null;
    date: string;
}
interface Account {
    id: number;
    name: string;
    color: string;
    balance: number;
    entries: Entry[];
}

defineProps<{ accounts: Account[]; can: { manage: boolean } }>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Savings' }];

const money = (n: number) =>
    new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD' }).format(n);

const forms = reactive<Record<number, { amount: string; note: string }>>({});
const busy = ref(false);
const openId = ref<number | null>(null);
const error = ref('');

function form(id: number) {
    return (forms[id] ??= { amount: '', note: '' });
}

/** sign: +1 adds money, -1 pays it out. */
async function submit(account: Account, sign: 1 | -1) {
    const f = form(account.id);
    const value = Math.abs(parseFloat(f.amount.replace(',', '.')));
    if (!value || busy.value) return;
    busy.value = true;
    error.value = '';
    const res = await api('POST', '/savings', {
        family_member_id: account.id,
        amount: sign * value,
        note: f.note || null,
    });
    busy.value = false;
    if (!res.ok) {
        error.value = res.message ?? 'Could not save that entry.';
        return;
    }
    f.amount = '';
    f.note = '';
    router.reload({ only: ['accounts'] });
}

async function remove(entry: Entry) {
    if ((await api('DELETE', `/savings/${entry.id}`)).ok) {
        router.reload({ only: ['accounts'] });
    }
}
</script>

<template>
    <Head title="Savings" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-3xl flex-col gap-5 p-4">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">Savings</h1>
                <p class="text-sm text-muted-foreground">
                    Money owed to or saved for each family member. Add allowance
                    or earnings, and record payouts when it's paid or spent.
                </p>
            </div>

            <p v-if="error" class="text-sm text-destructive" role="alert">{{ error }}</p>

            <div
                v-if="accounts.length === 0"
                class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground"
            >
                No accounts to show yet.
            </div>

            <section
                v-for="account in accounts"
                :key="account.id"
                class="space-y-3 rounded-lg border p-4"
            >
                <div class="flex items-center justify-between gap-3">
                    <h2 class="flex items-center gap-2 font-semibold">
                        <PiggyBank class="h-4 w-4 text-muted-foreground" />
                        {{ account.name }}
                    </h2>
                    <span
                        class="text-xl font-bold tabular-nums"
                        :class="account.balance < 0 ? 'text-destructive' : ''"
                    >
                        {{ money(account.balance) }}
                    </span>
                </div>

                <Button
                    v-if="can.manage && openId !== account.id"
                    variant="outline"
                    size="sm"
                    @click="openId = account.id"
                >
                    Add or pay out money
                </Button>
                <form
                    v-if="can.manage && openId === account.id"
                    class="flex flex-col gap-2 sm:flex-row"
                    @submit.prevent="submit(account, 1)"
                >
                    <Input
                        v-model="form(account.id).amount"
                        inputmode="decimal"
                        placeholder="Amount"
                        :aria-label="`Amount for ${account.name}`"
                        class="sm:w-32"
                    />
                    <Input
                        v-model="form(account.id).note"
                        maxlength="255"
                        placeholder="What for? (optional)"
                        :aria-label="`Note for ${account.name}`"
                    />
                    <div class="flex gap-2">
                        <Button type="submit" :disabled="busy">Add</Button>
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="busy"
                            @click="submit(account, -1)"
                        >
                            Pay out
                        </Button>
                    </div>
                </form>

                <ul v-if="account.entries.length" class="divide-y text-sm">
                    <li
                        v-for="entry in account.entries"
                        :key="entry.id"
                        class="flex items-center gap-3 py-2"
                    >
                        <span class="w-24 shrink-0 text-xs text-muted-foreground">
                            {{ entry.date }}
                        </span>
                        <span class="min-w-0 flex-1 truncate">
                            {{ entry.note || (entry.amount > 0 ? 'Added' : 'Paid out') }}
                        </span>
                        <span
                            class="font-medium tabular-nums"
                            :class="entry.amount > 0 ? 'text-emerald-700 dark:text-emerald-400' : ''"
                        >
                            {{ entry.amount > 0 ? '+' : '' }}{{ money(entry.amount) }}
                        </span>
                        <Button
                            v-if="can.manage"
                            variant="ghost"
                            size="icon"
                            class="h-8 w-8 text-muted-foreground hover:text-muted-foreground hover:text-destructive"
                            aria-label="Delete entry"
                            @click="remove(entry)"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                        </Button>
                    </li>
                </ul>
                <p v-else class="text-sm text-muted-foreground">Nothing recorded yet.</p>
            </section>
        </div>
    </AppLayout>
</template>
