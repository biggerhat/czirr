<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
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
import type { Bill, BudgetCategory } from '@/types/budgeting';

const props = defineProps<{
    bill: Bill | null;
    open: boolean;
    categories: BudgetCategory[];
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    saved: [];
}>();

const isEditing = computed(() => !!props.bill);
const isSaving = ref(false);
const errors = ref<Record<string, string[]>>({});

const name = ref('');
const amount = ref('');
const categoryId = ref('');
const startDate = ref('');
const frequency = ref<'monthly' | 'quarterly' | 'yearly'>('monthly');
const notes = ref('');
const isActive = ref(true);

// Inline add category
const showNewCategory = ref(false);
const newCategoryName = ref('');
const newCategoryColor = ref('blue');
const isAddingCategory = ref(false);

watch(() => props.open, (open) => {
    if (!open) return;
    errors.value = {};
    showNewCategory.value = false;

    if (props.bill) {
        name.value = props.bill.name;
        amount.value = String(props.bill.amount);
        categoryId.value = String(props.bill.budget_category_id);
        startDate.value = props.bill.start_date.slice(0, 10);
        frequency.value = props.bill.frequency;
        notes.value = props.bill.notes ?? '';
        isActive.value = props.bill.is_active;
    } else {
        name.value = '';
        amount.value = '';
        categoryId.value = props.categories.length > 0 ? String(props.categories[0].id) : '';
        startDate.value = new Date().toISOString().slice(0, 10);
        frequency.value = 'monthly';
        notes.value = '';
        isActive.value = true;
    }
});

async function addCategory() {
    if (!newCategoryName.value.trim()) return;
    isAddingCategory.value = true;

    try {
        const response = await fetch('/budget-categories', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': decodeURIComponent(
                    document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
                ),
            },
            body: JSON.stringify({ name: newCategoryName.value, color: newCategoryColor.value }),
        });

        if (response.ok) {
            const created = await response.json();
            categoryId.value = String(created.id);
            showNewCategory.value = false;
            newCategoryName.value = '';
            emit('saved');
        }
    } finally {
        isAddingCategory.value = false;
    }
}

async function save() {
    isSaving.value = true;
    errors.value = {};

    const body = {
        name: name.value,
        amount: parseFloat(amount.value),
        budget_category_id: parseInt(categoryId.value),
        start_date: startDate.value,
        frequency: frequency.value,
        is_active: isActive.value,
        notes: notes.value || null,
    };

    try {
        const url = isEditing.value ? `/bills/${props.bill!.id}` : '/bills';
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
                <SheetTitle>{{ isEditing ? 'Edit Bill' : 'New Bill' }}</SheetTitle>
            </SheetHeader>

            <form @submit.prevent="save" class="flex flex-1 flex-col">
                <div class="space-y-4 px-4 flex-1">
                    <div class="space-y-2">
                        <Label for="bill-name">Name</Label>
                        <Input id="bill-name" v-model="name" placeholder="e.g. Rent, Netflix" required />
                        <p v-if="errors.name" class="text-sm text-destructive">{{ errors.name[0] }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label for="bill-amount">Amount</Label>
                        <Input id="bill-amount" v-model="amount" type="number" step="0.01" min="0" placeholder="0.00" required />
                        <p v-if="errors.amount" class="text-sm text-destructive">{{ errors.amount[0] }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label>Category</Label>
                        <template v-if="!showNewCategory">
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
                            <Button type="button" variant="link" size="sm" class="px-0 h-auto" @click="showNewCategory = true">
                                + Add new category
                            </Button>
                        </template>
                        <template v-else>
                            <div class="flex gap-2">
                                <Input v-model="newCategoryName" placeholder="Category name" class="flex-1" />
                                <Button type="button" size="sm" :disabled="isAddingCategory" @click="addCategory">
                                    Add
                                </Button>
                                <Button type="button" variant="outline" size="sm" @click="showNewCategory = false">
                                    Cancel
                                </Button>
                            </div>
                        </template>
                        <p v-if="errors.budget_category_id" class="text-sm text-destructive">{{ errors.budget_category_id[0] }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label for="bill-start-date">Start Date</Label>
                        <Input id="bill-start-date" v-model="startDate" type="date" required />
                        <p v-if="errors.start_date" class="text-sm text-destructive">{{ errors.start_date[0] }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label>Frequency</Label>
                        <Select v-model="frequency">
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="monthly">Monthly</SelectItem>
                                <SelectItem value="quarterly">Quarterly</SelectItem>
                                <SelectItem value="yearly">Yearly</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-2">
                        <Label for="bill-notes">Notes</Label>
                        <Textarea id="bill-notes" v-model="notes" placeholder="Optional notes" />
                    </div>

                    <div class="flex items-center gap-2">
                        <Checkbox id="bill-active" :model-value="isActive" @update:model-value="isActive = $event as boolean" />
                        <Label for="bill-active" class="cursor-pointer">Active</Label>
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
