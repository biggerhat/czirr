<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Sheet,
    SheetContent,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import type { Income } from '@/types/budgeting';

const props = defineProps<{
    income: Income | null;
    open: boolean;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    saved: [];
}>();

const isEditing = computed(() => !!props.income);
const isSaving = ref(false);
const errors = ref<Record<string, string[]>>({});

const name = ref('');
const amount = ref('');
const startDate = ref('');
const frequency = ref<'once' | 'weekly' | 'biweekly' | 'monthly' | 'quarterly' | 'yearly'>('monthly');
const notes = ref('');
const isActive = ref(true);

watch(() => props.open, (open) => {
    if (!open) return;
    errors.value = {};

    if (props.income) {
        name.value = props.income.name;
        amount.value = String(props.income.amount);
        startDate.value = props.income.start_date.slice(0, 10);
        frequency.value = props.income.frequency;
        notes.value = props.income.notes ?? '';
        isActive.value = props.income.is_active;
    } else {
        name.value = '';
        amount.value = '';
        startDate.value = new Date().toISOString().slice(0, 10);
        frequency.value = 'monthly';
        notes.value = '';
        isActive.value = true;
    }
});

async function save() {
    isSaving.value = true;
    errors.value = {};

    const body = {
        name: name.value,
        amount: parseFloat(amount.value),
        start_date: startDate.value,
        frequency: frequency.value,
        is_active: isActive.value,
        notes: notes.value || null,
    };

    try {
        const url = isEditing.value ? `/incomes/${props.income!.id}` : '/incomes';
        const method = isEditing.value ? 'PUT' : 'POST';

        const response = await fetch(url, {
            method,
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
                <SheetTitle>{{ isEditing ? 'Edit Income' : 'New Income' }}</SheetTitle>
            </SheetHeader>

            <form @submit.prevent="save" class="flex flex-1 flex-col">
                <div class="space-y-4 px-4 flex-1">
                    <div class="space-y-2">
                        <Label for="income-name">Name</Label>
                        <Input id="income-name" v-model="name" placeholder="e.g. Salary, Freelance" required />
                        <p v-if="errors.name" class="text-sm text-destructive">{{ errors.name[0] }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label for="income-amount">Amount</Label>
                        <Input id="income-amount" v-model="amount" type="number" step="0.01" min="0" placeholder="0.00" required />
                        <p v-if="errors.amount" class="text-sm text-destructive">{{ errors.amount[0] }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label for="income-start-date">Start Date</Label>
                        <Input id="income-start-date" v-model="startDate" type="date" required />
                        <p v-if="errors.start_date" class="text-sm text-destructive">{{ errors.start_date[0] }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label>Frequency</Label>
                        <Select v-model="frequency">
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="once">Once</SelectItem>
                                <SelectItem value="weekly">Weekly</SelectItem>
                                <SelectItem value="biweekly">Biweekly</SelectItem>
                                <SelectItem value="monthly">Monthly</SelectItem>
                                <SelectItem value="quarterly">Quarterly</SelectItem>
                                <SelectItem value="yearly">Yearly</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-2">
                        <Label for="income-notes">Notes</Label>
                        <Textarea id="income-notes" v-model="notes" placeholder="Optional notes" />
                    </div>

                    <div class="flex items-center gap-2">
                        <Checkbox id="income-active" :model-value="isActive" @update:model-value="isActive = $event as boolean" />
                        <Label for="income-active" class="cursor-pointer">Active</Label>
                    </div>
                </div>

                <SheetFooter>
                    <div class="flex gap-2 w-full">
                        <Button type="button" variant="outline" class="flex-1" @click="$emit('update:open', false)">
                            Cancel
                        </Button>
                        <Button type="submit" class="flex-1" :disabled="isSaving">
                            {{ isSaving ? 'Saving...' : (isEditing ? 'Update' : 'Create') }}
                        </Button>
                    </div>
                </SheetFooter>
            </form>
        </SheetContent>
    </Sheet>
</template>
