<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    CalendarRange,
    Check,
    ChevronLeft,
    ChevronRight,
    DollarSign,
    LayoutGrid,
    List,
    Pencil,
    Plus,
    Receipt,
    Trash2,
    X,
} from 'lucide-vue-next';
import { ref, computed } from 'vue';
import BillModal from '@/components/budgeting/BillModal.vue';
import ExpenseModal from '@/components/budgeting/ExpenseModal.vue';
import IncomeModal from '@/components/budgeting/IncomeModal.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import { EVENT_COLORS } from '@/lib/calendar';
import type { BreadcrumbItem } from '@/types';
import type { Bill, BudgetCategory, Expense, Income } from '@/types/budgeting';

const props = defineProps<{
    categories: BudgetCategory[];
    bills: Bill[];
    expenses: Expense[];
    incomes: Income[];
    month: string;
    start_date: string;
    end_date: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Budget' },
];

// Month navigation
const currentMonth = computed(() => {
    const [y, m] = props.month.split('-').map(Number);
    return new Date(y, m - 1);
});

const monthTitle = computed(() => {
    return currentMonth.value.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
});

function navigateMonth(offset: number) {
    const d = currentMonth.value;
    d.setMonth(d.getMonth() + offset);
    const newMonth = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
    router.visit(`/budgeting?month=${newMonth}`, { preserveState: false });
}

function goToday() {
    router.visit('/budgeting', { preserveState: false });
}

// Date range
const rangeStart = ref(props.start_date);
const rangeEnd = ref(props.end_date);

const isCustomRange = computed(() => {
    const [y, m] = props.month.split('-').map(Number);
    const monthStart = `${y}-${String(m).padStart(2, '0')}-01`;
    const lastDay = new Date(y, m, 0).getDate();
    const monthEnd = `${y}-${String(m).padStart(2, '0')}-${String(lastDay).padStart(2, '0')}`;
    return props.start_date !== monthStart || props.end_date !== monthEnd;
});

const showDateRange = ref(isCustomRange.value);

const rangeTitle = computed(() => {
    if (!isCustomRange.value) return monthTitle.value;
    const fmt = (d: string) => new Date(d + 'T00:00:00').toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
    return `${fmt(props.start_date)} – ${fmt(props.end_date)}`;
});

function applyDateRange() {
    if (rangeStart.value && rangeEnd.value && rangeStart.value <= rangeEnd.value) {
        router.visit(`/budgeting?start=${rangeStart.value}&end=${rangeEnd.value}`, { preserveState: false });
    }
}

function clearDateRange() {
    router.visit(`/budgeting?month=${props.month}`, { preserveState: false });
}

// Summary computations
const totalBills = computed(() => props.bills.reduce((sum, b) => sum + Number(b.amount) * (b.occurrences_in_range ?? 1), 0));
const totalPaid = computed(() => props.bills.filter(b => b.is_paid_this_month).reduce((sum, b) => sum + Number(b.amount), 0));
const totalUnpaid = computed(() => totalBills.value - totalPaid.value);
const totalExpenses = computed(() => props.expenses.reduce((sum, e) => sum + Number(e.amount), 0));
const totalIncome = computed(() => props.incomes.filter(i => i.is_active).reduce((sum, i) => sum + Number(i.amount) * (i.occurrences_in_range ?? 1), 0));
const netBalance = computed(() => totalIncome.value - totalBills.value - totalExpenses.value);

// Bill category filter & grouping
const billCategoryFilter = ref<string>('all');
const groupByCategory = ref(false);
const filteredBills = computed(() => {
    if (billCategoryFilter.value === 'all') return props.bills;
    return props.bills.filter(b => String(b.budget_category_id) === billCategoryFilter.value);
});
const billsByCategory = computed(() => {
    const groups: { category: BudgetCategory; bills: Bill[] }[] = [];
    const map = new Map<number, Bill[]>();
    for (const bill of filteredBills.value) {
        const id = bill.budget_category_id;
        if (!map.has(id)) map.set(id, []);
        map.get(id)!.push(bill);
    }
    for (const cat of props.categories) {
        const bills = map.get(cat.id);
        if (bills?.length) groups.push({ category: cat, bills });
    }
    return groups;
});

// Modal state
const showBillModal = ref(false);
const editingBill = ref<Bill | null>(null);
const showExpenseModal = ref(false);
const expensePrefill = ref<{ name: string; amount: number; budget_category_id: number; bill_id: number } | null>(null);
const showIncomeModal = ref(false);
const editingIncome = ref<Income | null>(null);

function openCreateBill() {
    editingBill.value = null;
    showBillModal.value = true;
}

function openEditBill(bill: Bill) {
    editingBill.value = bill;
    showBillModal.value = true;
}

function openCreateExpense() {
    expensePrefill.value = null;
    showExpenseModal.value = true;
}

function openCreateIncome() {
    editingIncome.value = null;
    showIncomeModal.value = true;
}

function openEditIncome(income: Income) {
    editingIncome.value = income;
    showIncomeModal.value = true;
}

function onSaved() {
    router.reload();
}

// Toggle bill paid status
async function togglePaid(bill: Bill) {
    if (bill.is_paid_this_month) {
        // Find the expense for this bill this month and delete it
        const expense = props.expenses.find(e => e.bill_id === bill.id);
        if (expense) {
            await fetch(`/expenses/${expense.id}`, {
                method: 'DELETE',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': decodeURIComponent(
                        document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
                    ),
                },
            });
            router.reload();
        }
    } else {
        // Create a payment expense for this bill
        const [y, m] = props.month.split('-').map(Number);
        const dueDay = Math.min(new Date(bill.start_date).getUTCDate(), new Date(y, m, 0).getDate());
        const payDate = `${y}-${String(m).padStart(2, '0')}-${String(dueDay).padStart(2, '0')}`;

        await fetch('/expenses', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': decodeURIComponent(
                    document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
                ),
            },
            body: JSON.stringify({
                name: `${bill.name} payment`,
                amount: bill.amount,
                budget_category_id: bill.budget_category_id,
                date: payDate,
                bill_id: bill.id,
            }),
        });
        router.reload();
    }
}

// Delete dialog state
const showDeleteDialog = ref(false);
const deleteTarget = ref<{ type: 'bill'; item: Bill } | { type: 'income'; item: Income } | null>(null);
const isDeleting = ref(false);

function openDeleteBill(bill: Bill) {
    deleteTarget.value = { type: 'bill', item: bill };
    showDeleteDialog.value = true;
}

function openDeleteIncome(income: Income) {
    deleteTarget.value = { type: 'income', item: income };
    showDeleteDialog.value = true;
}

async function confirmDelete() {
    if (!deleteTarget.value) return;
    isDeleting.value = true;

    const url = deleteTarget.value.type === 'bill'
        ? `/bills/${deleteTarget.value.item.id}`
        : `/incomes/${deleteTarget.value.item.id}`;

    await fetch(url, {
        method: 'DELETE',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': decodeURIComponent(
                document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
            ),
        },
    });
    isDeleting.value = false;
    showDeleteDialog.value = false;
    router.reload();
}

async function deleteExpense(expense: Expense) {
    await fetch(`/expenses/${expense.id}`, {
        method: 'DELETE',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': decodeURIComponent(
                document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
            ),
        },
    });
    router.reload();
}

function formatCurrency(value: number): string {
    return new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD' }).format(value);
}

function ordinalDay(day: number): string {
    const s = ['th', 'st', 'nd', 'rd'];
    const v = day % 100;
    return day + (s[(v - 20) % 10] || s[v] || s[0]);
}

function getCategoryDotClass(category: BudgetCategory): string {
    return EVENT_COLORS[category.color]?.dot ?? 'bg-blue-500';
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <!-- Header: nav + title + date range toggle -->
            <div class="flex items-center gap-2">
                <Button variant="outline" size="icon" @click="navigateMonth(-1)">
                    <ChevronLeft class="h-4 w-4" />
                </Button>
                <Button variant="outline" size="icon" @click="navigateMonth(1)">
                    <ChevronRight class="h-4 w-4" />
                </Button>
                <Button variant="outline" size="sm" @click="goToday">
                    Today
                </Button>
                <h2 class="text-lg font-semibold ml-2 flex-1 truncate">{{ rangeTitle }}</h2>
                <Button
                    variant="ghost"
                    size="icon"
                    class="h-8 w-8"
                    :class="{ 'bg-accent': showDateRange }"
                    @click="showDateRange = !showDateRange"
                >
                    <CalendarRange class="h-4 w-4" />
                </Button>
            </div>

            <!-- Collapsible Date Range -->
            <div v-if="showDateRange" class="flex flex-col sm:flex-row sm:items-center gap-2">
                <Input type="date" v-model="rangeStart" class="w-full sm:w-[160px] h-8 text-sm" />
                <span class="text-sm text-muted-foreground hidden sm:inline">to</span>
                <Input type="date" v-model="rangeEnd" class="w-full sm:w-[160px] h-8 text-sm" />
                <div class="flex items-center gap-2">
                    <Button size="sm" variant="outline" class="h-8 w-full sm:w-auto" @click="applyDateRange" :disabled="!rangeStart || !rangeEnd || rangeStart > rangeEnd">
                        Apply
                    </Button>
                    <Button v-if="isCustomRange" size="sm" variant="ghost" class="h-8 w-full sm:w-auto" @click="clearDateRange">
                        <X class="h-3.5 w-3.5 mr-1" />
                        Reset
                    </Button>
                </div>
            </div>

            <!-- Summary Stats -->
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-px rounded-lg border bg-border overflow-hidden">
                <div class="bg-card p-3">
                    <p class="text-xs font-medium text-muted-foreground">Income</p>
                    <p class="text-lg font-bold text-emerald-600 dark:text-emerald-400">{{ formatCurrency(totalIncome) }}</p>
                </div>
                <div class="bg-card p-3">
                    <p class="text-xs font-medium text-muted-foreground">Bills</p>
                    <p class="text-lg font-bold">{{ formatCurrency(totalBills) }}</p>
                </div>
                <div class="bg-card p-3">
                    <p class="text-xs font-medium text-muted-foreground">Paid</p>
                    <p class="text-lg font-bold text-emerald-600 dark:text-emerald-400">{{ formatCurrency(totalPaid) }}</p>
                </div>
                <div class="bg-card p-3">
                    <p class="text-xs font-medium text-muted-foreground">Unpaid</p>
                    <p class="text-lg font-bold text-rose-600 dark:text-rose-400">{{ formatCurrency(totalUnpaid) }}</p>
                </div>
                <div class="bg-card p-3">
                    <p class="text-xs font-medium text-muted-foreground">Expenses</p>
                    <p class="text-lg font-bold">{{ formatCurrency(totalExpenses) }}</p>
                </div>
                <div class="bg-card p-3">
                    <p class="text-xs font-medium text-muted-foreground">Net</p>
                    <p class="text-lg font-bold" :class="netBalance >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'">
                        {{ formatCurrency(netBalance) }}
                    </p>
                </div>
            </div>

            <!-- Income Section -->
            <div>
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-lg font-semibold">Income</h3>
                    <Button size="sm" class="bg-emerald-100 text-emerald-700 border border-emerald-300 hover:bg-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-300 dark:border-emerald-700 dark:hover:bg-emerald-900/50" @click="openCreateIncome">
                        <DollarSign class="h-4 w-4 mr-1" />
                        Add Income
                    </Button>
                </div>
                <div v-if="incomes.length === 0" class="rounded-lg border border-dashed p-8 text-center">
                    <DollarSign class="mx-auto mb-2 h-8 w-8 text-muted-foreground/30" />
                    <p class="text-sm text-muted-foreground">No income sources yet.</p>
                    <p class="mt-1 text-xs text-muted-foreground/70">Click "Add Income" to get started.</p>
                </div>
                <div v-else class="space-y-2">
                    <div
                        v-for="income in incomes"
                        :key="income.id"
                        class="flex items-center gap-3 rounded-lg border px-4 py-3"
                        :class="{ 'opacity-50': !income.is_active }"
                    >
                        <div class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-3 flex-1 min-w-0">
                            <div class="min-w-0">
                                <span class="font-medium">{{ income.name }}</span>
                                <span class="ml-2 text-sm text-muted-foreground">
                                    {{ ordinalDay(new Date(income.start_date).getUTCDate()) }}
                                    <span class="capitalize"> &middot; {{ income.frequency }}</span>
                                </span>
                            </div>
                            <span class="font-semibold tabular-nums text-emerald-600 dark:text-emerald-400 sm:ml-auto">{{ formatCurrency(Number(income.amount)) }}</span>
                        </div>

                        <div class="flex items-center gap-1">
                            <Button variant="ghost" size="icon" class="h-8 w-8" @click="openEditIncome(income)">
                                <Pencil class="h-3.5 w-3.5" />
                            </Button>
                            <Button variant="ghost" size="icon" class="h-8 w-8 text-destructive" @click="openDeleteIncome(income)">
                                <Trash2 class="h-3.5 w-3.5" />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bills Section -->
            <div>
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-3">
                    <h3 class="text-lg font-semibold">Bills</h3>
                    <div class="flex flex-wrap items-center gap-2">
                        <Button
                            variant="ghost"
                            size="icon"
                            class="h-8 w-8"
                            :class="{ 'bg-accent': groupByCategory }"
                            title="Group by category"
                            @click="groupByCategory = !groupByCategory"
                        >
                            <LayoutGrid v-if="groupByCategory" class="h-4 w-4" />
                            <List v-else class="h-4 w-4" />
                        </Button>
                        <Select v-model="billCategoryFilter">
                            <SelectTrigger class="w-full sm:w-[200px]">
                                <SelectValue placeholder="All Categories" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Categories</SelectItem>
                                <SelectItem v-for="cat in categories" :key="cat.id" :value="String(cat.id)">
                                    <span class="flex items-center gap-2">
                                        <span class="h-2.5 w-2.5 shrink-0 rounded-full" :class="getCategoryDotClass(cat)" />
                                        {{ cat.name }}
                                    </span>
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <Button size="sm" class="bg-rose-100 text-rose-700 border border-rose-300 hover:bg-rose-200 dark:bg-rose-900/30 dark:text-rose-300 dark:border-rose-700 dark:hover:bg-rose-900/50" @click="openCreateBill">
                            <Plus class="h-4 w-4 mr-1" />
                            Add Bill
                        </Button>
                    </div>
                </div>
                <div v-if="bills.length === 0" class="rounded-lg border border-dashed p-8 text-center">
                    <Receipt class="mx-auto mb-2 h-8 w-8 text-muted-foreground/30" />
                    <p class="text-sm text-muted-foreground">No bills yet.</p>
                    <p class="mt-1 text-xs text-muted-foreground/70">Click "Add Bill" to get started.</p>
                </div>
                <div v-else-if="filteredBills.length === 0" class="rounded-lg border border-dashed p-8 text-center">
                    <Receipt class="mx-auto mb-2 h-8 w-8 text-muted-foreground/30" />
                    <p class="text-sm text-muted-foreground">No bills in this category.</p>
                    <p class="mt-1 text-xs text-muted-foreground/70">Try selecting a different category.</p>
                </div>

                <!-- Grouped view -->
                <div v-else-if="groupByCategory" class="space-y-4">
                    <div v-for="group in billsByCategory" :key="group.category.id">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="h-2.5 w-2.5 shrink-0 rounded-full" :class="getCategoryDotClass(group.category)" />
                            <span class="text-sm font-medium text-muted-foreground">{{ group.category.name }}</span>
                            <span class="text-xs text-muted-foreground">({{ formatCurrency(group.bills.reduce((s, b) => s + Number(b.amount) * (b.occurrences_in_range ?? 1), 0)) }})</span>
                        </div>
                        <div class="space-y-2">
                            <div
                                v-for="bill in group.bills"
                                :key="bill.id"
                                class="flex items-center gap-3 rounded-lg border px-4 py-3 cursor-pointer"
                                :class="{ 'opacity-50': !bill.is_active }"
                                @click="togglePaid(bill)"
                            >
                                <div
                                    class="flex h-5 w-5 shrink-0 items-center justify-center rounded border transition-colors"
                                    :class="bill.is_paid_this_month
                                        ? 'bg-emerald-500 border-emerald-500 text-white'
                                        : 'border-muted-foreground/30'"
                                >
                                    <Check v-if="bill.is_paid_this_month" class="h-3 w-3" />
                                </div>

                                <div class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-3 flex-1 min-w-0">
                                    <div class="min-w-0">
                                        <span class="font-medium" :class="{ 'line-through text-muted-foreground': bill.is_paid_this_month }">
                                            {{ bill.name }}
                                        </span>
                                        <span class="ml-2 text-sm text-muted-foreground">
                                            Due {{ ordinalDay(new Date(bill.start_date).getUTCDate()) }}
                                            <span v-if="bill.frequency !== 'monthly'" class="capitalize"> &middot; {{ bill.frequency }}</span>
                                        </span>
                                    </div>
                                    <span class="font-semibold tabular-nums sm:ml-auto">{{ formatCurrency(Number(bill.amount)) }}</span>
                                </div>

                                <div class="flex items-center gap-1">
                                    <Button variant="ghost" size="icon" class="h-8 w-8" @click.stop="openEditBill(bill)">
                                        <Pencil class="h-3.5 w-3.5" />
                                    </Button>
                                    <Button variant="ghost" size="icon" class="h-8 w-8 text-destructive" @click.stop="openDeleteBill(bill)">
                                        <Trash2 class="h-3.5 w-3.5" />
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Flat view -->
                <div v-else class="space-y-2">
                    <div
                        v-for="bill in filteredBills"
                        :key="bill.id"
                        class="flex items-center gap-3 rounded-lg border px-4 py-3 cursor-pointer"
                        :class="{ 'opacity-50': !bill.is_active }"
                        @click="togglePaid(bill)"
                    >
                        <div
                            class="flex h-5 w-5 shrink-0 items-center justify-center rounded border transition-colors"
                            :class="bill.is_paid_this_month
                                ? 'bg-emerald-500 border-emerald-500 text-white'
                                : 'border-muted-foreground/30'"
                        >
                            <Check v-if="bill.is_paid_this_month" class="h-3 w-3" />
                        </div>

                        <span
                            class="h-2.5 w-2.5 shrink-0 rounded-full"
                            :class="getCategoryDotClass(bill.category)"
                        />

                        <div class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-3 flex-1 min-w-0">
                            <div class="min-w-0">
                                <span class="font-medium" :class="{ 'line-through text-muted-foreground': bill.is_paid_this_month }">
                                    {{ bill.name }}
                                </span>
                                <span class="ml-2 text-sm text-muted-foreground">
                                    Due {{ ordinalDay(new Date(bill.start_date).getUTCDate()) }}
                                    <span v-if="bill.frequency !== 'monthly'" class="capitalize"> &middot; {{ bill.frequency }}</span>
                                </span>
                            </div>
                            <span class="font-semibold tabular-nums sm:ml-auto">{{ formatCurrency(Number(bill.amount)) }}</span>
                        </div>

                        <div class="flex items-center gap-1">
                            <Button variant="ghost" size="icon" class="h-8 w-8" @click.stop="openEditBill(bill)">
                                <Pencil class="h-3.5 w-3.5" />
                            </Button>
                            <Button variant="ghost" size="icon" class="h-8 w-8 text-destructive" @click.stop="openDeleteBill(bill)">
                                <Trash2 class="h-3.5 w-3.5" />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Expenses Section -->
            <div>
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-lg font-semibold">Expenses</h3>
                    <Button size="sm" class="bg-blue-100 text-blue-700 border border-blue-300 hover:bg-blue-200 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-700 dark:hover:bg-blue-900/50" @click="openCreateExpense">
                        <Receipt class="h-4 w-4 mr-1" />
                        Add Expense
                    </Button>
                </div>
                <div v-if="expenses.length === 0" class="rounded-lg border border-dashed p-8 text-center">
                    <Receipt class="mx-auto mb-2 h-8 w-8 text-muted-foreground/30" />
                    <p class="text-sm text-muted-foreground">No expenses this month.</p>
                    <p class="mt-1 text-xs text-muted-foreground/70">Expenses and bill payments will appear here.</p>
                </div>
                <div v-else class="space-y-2">
                    <div
                        v-for="expense in expenses"
                        :key="expense.id"
                        class="flex items-center gap-3 rounded-lg border px-4 py-3"
                    >
                        <span
                            class="h-2.5 w-2.5 shrink-0 rounded-full"
                            :class="getCategoryDotClass(expense.category)"
                        />

                        <div class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-3 flex-1 min-w-0">
                            <div class="min-w-0">
                                <span class="font-medium">{{ expense.name }}</span>
                                <span class="ml-2 text-sm text-muted-foreground">
                                    {{ new Date(expense.date).toLocaleDateString(undefined, { month: 'short', day: 'numeric', timeZone: 'UTC' }) }}
                                </span>
                                <span v-if="expense.bill_id" class="ml-1 text-xs text-muted-foreground">(bill payment)</span>
                            </div>
                            <span class="font-semibold tabular-nums sm:ml-auto">{{ formatCurrency(Number(expense.amount)) }}</span>
                        </div>

                        <Button variant="ghost" size="icon" class="h-8 w-8 text-destructive" @click="deleteExpense(expense)">
                            <Trash2 class="h-3.5 w-3.5" />
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Delete dialog -->
            <Dialog :open="showDeleteDialog" @update:open="showDeleteDialog = $event">
                <DialogContent class="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Delete {{ deleteTarget?.type === 'bill' ? 'bill' : 'income' }}?</DialogTitle>
                        <DialogDescription>
                            This will permanently delete "{{ deleteTarget?.item.name }}" and its linked calendar event. This cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter class="gap-2">
                        <Button variant="outline" @click="showDeleteDialog = false">Cancel</Button>
                        <Button variant="destructive" :disabled="isDeleting" @click="confirmDelete">
                            {{ isDeleting ? 'Deleting...' : 'Delete' }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Modals -->
            <BillModal
                :bill="editingBill"
                :open="showBillModal"
                :categories="categories"
                @update:open="showBillModal = $event"
                @saved="onSaved"
            />

            <ExpenseModal
                :open="showExpenseModal"
                :categories="categories"
                :prefill="expensePrefill"
                @update:open="showExpenseModal = $event"
                @saved="onSaved"
            />

            <IncomeModal
                :income="editingIncome"
                :open="showIncomeModal"
                @update:open="showIncomeModal = $event"
                @saved="onSaved"
            />
        </div>
    </AppLayout>
</template>
