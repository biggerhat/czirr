<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    Sheet,
    SheetContent,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { BudgetCategory } from '@/types/budgeting';

const props = defineProps<{
    open: boolean;
    categories: BudgetCategory[];
    defaultDate?: string;
    prefill?: {
        name: string;
        amount: number;
        budget_category_id: number;
        bill_id: number;
    } | null;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    saved: [];
}>();

const isSaving = ref(false);
const errors = ref<Record<string, string[]>>({});

const name = ref('');
const amount = ref('');
const categoryId = ref('');
const date = ref('');
const notes = ref('');

watch(() => props.open, (open) => {
    if (!open) return;
    errors.value = {};

    if (props.prefill) {
        name.value = props.prefill.name;
        amount.value = String(props.prefill.amount);
        categoryId.value = String(props.prefill.budget_category_id);
    } else {
        name.value = '';
        amount.value = '';
        categoryId.value = props.categories.length > 0 ? String(props.categories[0].id) : '';
    }

    const today = new Date();
    date.value = props.defaultDate ?? `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
    notes.value = '';
});

async function save() {
    isSaving.value = true;
    errors.value = {};

    const body: Record<string, unknown> = {
        name: name.value,
        amount: parseFloat(amount.value),
        budget_category_id: parseInt(categoryId.value),
        date: date.value,
        notes: notes.value || null,
    };

    if (props.prefill?.bill_id) {
        body.bill_id = props.prefill.bill_id;
    }

    try {
        const response = await fetch('/expenses', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': decodeURIComponent(
                    document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
                ),
            },
            body: JSON.stringify(body),
        });

        if (response.ok) {
            emit('saved');
            emit('update:open', false);
        } else if (response.status === 422) {
            const data = await response.json();
            errors.value = data.errors ?? {};
        } else {
            errors.value = { name: [`Save failed (${response.status}). Please try again.`] };
        }
    } finally {
        isSaving.value = false;
    }
}
</script>

<template>
    <Sheet :open="open" @update:open="$emit('update:open', $event)">
        <SheetContent side="right" class="flex flex-col overflow-y-auto">
            <SheetHeader>
                <SheetTitle>Add Expense</SheetTitle>
            </SheetHeader>

            <form @submit.prevent="save" class="flex flex-1 flex-col">
                <div class="space-y-4 px-4 flex-1">
                    <div class="space-y-2">
                        <Label for="expense-name">Name</Label>
                        <Input id="expense-name" v-model="name" placeholder="e.g. Groceries" required />
                        <p v-if="errors.name" class="text-sm text-destructive">{{ errors.name[0] }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label for="expense-amount">Amount</Label>
                        <Input id="expense-amount" v-model="amount" type="number" step="0.01" min="0" placeholder="0.00" required />
                        <p v-if="errors.amount" class="text-sm text-destructive">{{ errors.amount[0] }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label>Category</Label>
                        <Select v-model="categoryId">
                            <SelectTrigger>
                                <SelectValue placeholder="Select category" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="cat in categories"
                                    :key="cat.id"
                                    :value="String(cat.id)"
                                >
                                    {{ cat.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="errors.budget_category_id" class="text-sm text-destructive">{{ errors.budget_category_id[0] }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label for="expense-date">Date</Label>
                        <Input id="expense-date" v-model="date" type="date" required />
                        <p v-if="errors.date" class="text-sm text-destructive">{{ errors.date[0] }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label for="expense-notes">Notes</Label>
                        <Textarea id="expense-notes" v-model="notes" placeholder="Optional notes" />
                    </div>
                </div>

                <SheetFooter>
                    <div class="flex gap-2 w-full">
                        <Button type="button" variant="outline" class="flex-1" @click="$emit('update:open', false)">
                            Cancel
                        </Button>
                        <Button type="submit" class="flex-1" :disabled="isSaving">
                            {{ isSaving ? 'Saving...' : 'Create' }}
                        </Button>
                    </div>
                </SheetFooter>
            </form>
        </SheetContent>
    </Sheet>
</template>
