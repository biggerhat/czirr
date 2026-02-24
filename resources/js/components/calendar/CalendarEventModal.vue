<script setup lang="ts">
import { CalendarDays, FileText, DollarSign, Receipt, Check, Plus } from 'lucide-vue-next';
import { ref, computed, watch, nextTick } from 'vue';
import RecurrenceEditor from '@/components/calendar/RecurrenceEditor.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
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
import { EVENT_COLORS, toLocalDateString } from '@/lib/calendar';
import type { RecurrenceConfig } from '@/lib/recurrence';
import { defaultRecurrenceConfig, buildRRuleString, parseRRuleString } from '@/lib/recurrence';
import type { BudgetCategory } from '@/types/budgeting';
import type { CalendarEvent, EditMode, EventType, FamilyMember } from '@/types/calendar';

export type EntryType = 'event' | 'bill' | 'expense' | 'income';

const props = defineProps<{
    event: CalendarEvent | null;
    open: boolean;
    timezone: string;
    familyMembers: FamilyMember[];
    categories: BudgetCategory[];
    eventTypes: EventType[];
    defaultDate: string | null;
    defaultEntryType: EntryType;
    editMode: EditMode | null;
    occurrenceStart: string | null;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    saved: [event: CalendarEvent, isRecurring: boolean];
    'budget-saved': [];
}>();

const entryType = ref<EntryType>('event');
const isEditing = computed(() => !!props.event);
const isSaving = ref(false);
const errors = ref<Record<string, string[]>>({});

// --- Event fields ---
const title = ref('');
const description = ref('');
const startDate = ref('');
const startTime = ref('09:00');
const endDate = ref('');
const endTime = ref('10:00');
const isAllDay = ref(false);
const familyMemberIds = ref<number[]>([]);
const recurrenceConfig = ref<RecurrenceConfig>(defaultRecurrenceConfig());
const eventTypeId = ref('none');
const allEventTypes = ref<EventType[]>([]);
const newTypeName = ref('');
const isCreatingType = ref(false);
const showTypeCreate = ref(false);

// --- Budget shared fields ---
const budgetName = ref('');
const budgetAmount = ref('');
const budgetNotes = ref('');
const budgetCategoryId = ref('');

// --- Bill fields ---
const billStartDate = ref('');
const billFrequency = ref<'once' | 'weekly' | 'biweekly' | 'monthly' | 'quarterly' | 'yearly'>('monthly');
const billIsActive = ref(true);

// --- Expense fields ---
const expenseDate = ref('');

// --- Income fields ---
const incomeStartDate = ref('');
const incomeFrequency = ref<'once' | 'weekly' | 'biweekly' | 'monthly' | 'quarterly' | 'yearly'>('monthly');
const incomeIsActive = ref(true);


// Hide recurrence editor when editing a single occurrence
const showRecurrenceEditor = computed(() => props.editMode !== 'single');

// Guard to skip auto-correction while populating the form
const isInitializing = ref(false);

const sheetTitle = computed(() => {
    if (isEditing.value) return 'Edit Event';
    return {
        event: 'New Event',
        bill: 'New Bill',
        expense: 'New Expense',
        income: 'New Income',
    }[entryType.value];
});

const entryTypes: { value: EntryType; label: string; icon: typeof CalendarDays }[] = [
    { value: 'event', label: 'Event', icon: CalendarDays },
    { value: 'bill', label: 'Bill', icon: FileText },
    { value: 'expense', label: 'Expense', icon: Receipt },
    { value: 'income', label: 'Income', icon: DollarSign },
];

// Auto-correct end date/time when start moves past end
watch([startDate, startTime], () => {
    if (isInitializing.value) return;
    if (startDate.value > endDate.value) {
        endDate.value = startDate.value;
    }
    if (!isAllDay.value && startDate.value === endDate.value && startTime.value >= endTime.value) {
        const [h, m] = startTime.value.split(':').map(Number);
        const newH = Math.min(h + 1, 23);
        endTime.value = `${String(newH).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
    }
});

watch(endDate, () => {
    if (isInitializing.value) return;
    if (endDate.value < startDate.value) {
        endDate.value = startDate.value;
    }
    if (!isAllDay.value && startDate.value === endDate.value && endTime.value <= startTime.value) {
        const [h, m] = startTime.value.split(':').map(Number);
        const newH = Math.min(h + 1, 23);
        endTime.value = `${String(newH).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
    }
});

watch(endTime, () => {
    if (isInitializing.value) return;
    if (!isAllDay.value && startDate.value === endDate.value && endTime.value <= startTime.value) {
        const [h, m] = startTime.value.split(':').map(Number);
        const newH = Math.min(h + 1, 23);
        endTime.value = `${String(newH).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
    }
});

const endDateError = computed(() => {
    if (isAllDay.value) {
        return endDate.value < startDate.value ? 'End date cannot be before start date' : null;
    }
    if (endDate.value < startDate.value) return 'End date cannot be before start date';
    if (startDate.value === endDate.value && endTime.value <= startTime.value) {
        return 'End time must be after start time';
    }
    return null;
});

watch(() => props.open, (open) => {
    if (!open) return;
    errors.value = {};
    isInitializing.value = true;

    // Set entry type
    entryType.value = isEditing.value ? 'event' : props.defaultEntryType;

    allEventTypes.value = [...props.eventTypes];

    if (props.event) {
        // Editing an existing event
        title.value = props.event.title;
        description.value = props.event.description ?? '';
        isAllDay.value = props.event.is_all_day;
        familyMemberIds.value = (props.event.family_members ?? []).map(fm => fm.id);
        recurrenceConfig.value = parseRRuleString(props.event.rrule);
        eventTypeId.value = props.event.event_type_id ? String(props.event.event_type_id) : 'none';

        const start = new Date(props.event.starts_at);
        const end = new Date(props.event.ends_at);
        startDate.value = toLocalDateString(start, props.timezone);
        endDate.value = toLocalDateString(end, props.timezone);

        if (!props.event.is_all_day) {
            startTime.value = start.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', timeZone: props.timezone });
            endTime.value = end.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', timeZone: props.timezone });
        }
    } else {
        const today = props.defaultDate ?? toLocalDateString(new Date(), props.timezone);

        // Event defaults
        title.value = '';
        description.value = '';
        startDate.value = today;
        endDate.value = today;
        startTime.value = '09:00';
        endTime.value = '10:00';
        isAllDay.value = false;
        familyMemberIds.value = [];
        recurrenceConfig.value = defaultRecurrenceConfig();
        eventTypeId.value = 'none';

        // Budget defaults
        budgetName.value = '';
        budgetAmount.value = '';
        budgetNotes.value = '';
        budgetCategoryId.value = props.categories.length > 0 ? String(props.categories[0].id) : '';

        // Bill
        billStartDate.value = today;
        billFrequency.value = 'monthly';
        billIsActive.value = true;

        // Expense
        expenseDate.value = today;

        // Income
        incomeStartDate.value = today;
        incomeFrequency.value = 'monthly';
        incomeIsActive.value = true;
    }

    nextTick(() => {
        isInitializing.value = false;
    });
});

function toggleFamilyMember(id: number) {
    if (familyMemberIds.value.includes(id)) {
        familyMemberIds.value = familyMemberIds.value.filter(i => i !== id);
    } else {
        familyMemberIds.value = [...familyMemberIds.value, id];
    }
}

function getHeaders() {
    return {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': decodeURIComponent(
            document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
        ),
    };
}

async function createEventType() {
    if (!newTypeName.value.trim()) return;
    isCreatingType.value = true;
    try {
        const response = await fetch('/event-types', {
            method: 'POST',
            headers: getHeaders(),
            body: JSON.stringify({ name: newTypeName.value.trim() }),
        });
        if (response.ok) {
            const created: EventType = await response.json();
            allEventTypes.value.push(created);
            allEventTypes.value.sort((a, b) => a.name.localeCompare(b.name));
            eventTypeId.value = String(created.id);
            newTypeName.value = '';
            showTypeCreate.value = false;
        }
    } finally {
        isCreatingType.value = false;
    }
}

async function saveEvent() {
    const rrule = showRecurrenceEditor.value ? buildRRuleString(recurrenceConfig.value) : null;
    const isRecurring = !!rrule || !!props.event?.rrule || !!props.event?.is_occurrence;

    const body: Record<string, unknown> = {
        title: title.value,
        description: description.value || null,
        start_date: startDate.value,
        start_time: startTime.value,
        end_date: endDate.value,
        end_time: endTime.value,
        is_all_day: isAllDay.value,
        family_member_ids: familyMemberIds.value,
        timezone: props.timezone,
        rrule,
        event_type_id: eventTypeId.value !== 'none' ? parseInt(eventTypeId.value) : null,
    };

    if (props.editMode) {
        body.edit_mode = props.editMode;
    }
    if (props.occurrenceStart) {
        body.occurrence_start = props.occurrenceStart;
    }

    let eventId: number | string | undefined;
    if (isEditing.value) {
        eventId = props.event!.master_event_id ?? props.event!.id;
    }

    const url = isEditing.value ? `/events/${eventId}` : '/events';
    const method = isEditing.value ? 'PUT' : 'POST';

    const response = await fetch(url, {
        method,
        headers: getHeaders(),
        body: JSON.stringify(body),
    });

    if (response.ok) {
        const saved = await response.json();
        emit('saved', saved, isRecurring);
    } else if (response.status === 422) {
        const data = await response.json();
        errors.value = data.errors ?? {};
    } else {
        errors.value = { title: [`Save failed (${response.status}). Please try again.`] };
    }
}

async function saveBill() {
    const body = {
        name: budgetName.value,
        amount: parseFloat(budgetAmount.value),
        budget_category_id: parseInt(budgetCategoryId.value),
        start_date: billStartDate.value,
        frequency: billFrequency.value,
        is_active: billIsActive.value,
        notes: budgetNotes.value || null,
    };

    const response = await fetch('/bills', {
        method: 'POST',
        headers: getHeaders(),
        body: JSON.stringify(body),
    });

    if (response.ok) {
        emit('budget-saved');
        emit('update:open', false);
    } else if (response.status === 422) {
        const data = await response.json();
        errors.value = data.errors ?? {};
    } else {
        errors.value = { name: [`Save failed (${response.status}). Please try again.`] };
    }
}

async function saveExpense() {
    const body = {
        name: budgetName.value,
        amount: parseFloat(budgetAmount.value),
        budget_category_id: parseInt(budgetCategoryId.value),
        date: expenseDate.value,
        notes: budgetNotes.value || null,
    };

    const response = await fetch('/expenses', {
        method: 'POST',
        headers: getHeaders(),
        body: JSON.stringify(body),
    });

    if (response.ok) {
        emit('budget-saved');
        emit('update:open', false);
    } else if (response.status === 422) {
        const data = await response.json();
        errors.value = data.errors ?? {};
    } else {
        errors.value = { name: [`Save failed (${response.status}). Please try again.`] };
    }
}

async function saveIncome() {
    const body = {
        name: budgetName.value,
        amount: parseFloat(budgetAmount.value),
        start_date: incomeStartDate.value,
        frequency: incomeFrequency.value,
        is_active: incomeIsActive.value,
        notes: budgetNotes.value || null,
    };

    const response = await fetch('/incomes', {
        method: 'POST',
        headers: getHeaders(),
        body: JSON.stringify(body),
    });

    if (response.ok) {
        emit('budget-saved');
        emit('update:open', false);
    } else if (response.status === 422) {
        const data = await response.json();
        errors.value = data.errors ?? {};
    } else {
        errors.value = { name: [`Save failed (${response.status}). Please try again.`] };
    }
}

async function save() {
    isSaving.value = true;
    errors.value = {};

    try {
        if (isEditing.value || entryType.value === 'event') {
            await saveEvent();
        } else if (entryType.value === 'bill') {
            await saveBill();
        } else if (entryType.value === 'expense') {
            await saveExpense();
        } else if (entryType.value === 'income') {
            await saveIncome();
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
                <SheetTitle>{{ sheetTitle }}</SheetTitle>
            </SheetHeader>

            <form @submit.prevent="save" class="flex flex-1 flex-col">
                <div class="space-y-4 px-4 flex-1">
                    <!-- Type selector (create only) -->
                    <div v-if="!isEditing" class="grid grid-cols-4 gap-1 rounded-lg bg-muted p-1">
                        <button
                            v-for="t in entryTypes"
                            :key="t.value"
                            type="button"
                            class="flex items-center justify-center gap-1.5 rounded-md px-2 py-1.5 text-sm font-medium transition-colors"
                            :class="entryType === t.value
                                ? 'bg-background text-foreground shadow-sm'
                                : 'text-muted-foreground hover:text-foreground'"
                            @click="entryType = t.value; errors = {}"
                        >
                            <component :is="t.icon" class="h-3.5 w-3.5" />
                            {{ t.label }}
                        </button>
                    </div>

                    <!-- ==================== EVENT FIELDS ==================== -->
                    <template v-if="isEditing || entryType === 'event'">
                        <div class="space-y-2">
                            <Label for="title">Title</Label>
                            <Input id="title" v-model="title" placeholder="Event title" required />
                            <p v-if="errors.title" class="text-sm text-destructive">{{ errors.title[0] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="description">Description</Label>
                            <Textarea id="description" v-model="description" placeholder="Optional description" rows="2" />
                        </div>

                        <div class="space-y-2">
                            <Label>Event Type</Label>
                            <div class="flex items-center gap-2">
                                <Select v-model="eventTypeId">
                                    <SelectTrigger class="flex-1">
                                        <SelectValue placeholder="None" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">None</SelectItem>
                                        <SelectItem
                                            v-for="et in allEventTypes"
                                            :key="et.id"
                                            :value="String(et.id)"
                                        >
                                            {{ et.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <Popover v-model:open="showTypeCreate">
                                    <PopoverTrigger as-child>
                                        <Button type="button" variant="outline" size="icon" class="shrink-0">
                                            <Plus class="h-4 w-4" />
                                        </Button>
                                    </PopoverTrigger>
                                    <PopoverContent class="w-64 p-3">
                                        <form @submit.prevent="createEventType" class="flex flex-col gap-2">
                                            <Label class="text-sm">New Event Type</Label>
                                            <Input v-model="newTypeName" placeholder="e.g. Meetings" class="h-8 text-sm" />
                                            <Button type="submit" size="sm" :disabled="isCreatingType || !newTypeName.trim()">
                                                {{ isCreatingType ? 'Adding...' : 'Add' }}
                                            </Button>
                                        </form>
                                    </PopoverContent>
                                </Popover>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <Checkbox id="is_all_day" :model-value="isAllDay" @update:model-value="isAllDay = !!$event" />
                            <Label for="is_all_day" class="cursor-pointer">All day event</Label>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label for="start_date">Start Date</Label>
                                <Input id="start_date" type="date" v-model="startDate" required />
                            </div>
                            <div v-if="!isAllDay" class="space-y-2">
                                <Label for="start_time">Start Time</Label>
                                <Input id="start_time" type="time" v-model="startTime" required />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label for="end_date">End Date</Label>
                                <Input id="end_date" type="date" v-model="endDate" :min="startDate" required />
                            </div>
                            <div v-if="!isAllDay" class="space-y-2">
                                <Label for="end_time">End Time</Label>
                                <Input id="end_time" type="time" v-model="endTime" required />
                            </div>
                        </div>
                        <p v-if="endDateError" class="text-sm text-destructive">
                            {{ endDateError }}
                        </p>
                        <p v-else-if="errors.starts_at || errors.ends_at" class="text-sm text-destructive">
                            {{ errors.starts_at?.[0] || errors.ends_at?.[0] }}
                        </p>

                        <RecurrenceEditor v-if="showRecurrenceEditor" v-model="recurrenceConfig" />

                        <div v-if="familyMembers.length > 0" class="space-y-2">
                            <Label>Family Members</Label>
                            <div class="space-y-1.5">
                                <div
                                    v-for="member in familyMembers"
                                    :key="member.id"
                                    class="flex items-center gap-2 rounded-md px-2 py-1.5 hover:bg-muted cursor-pointer"
                                    @click="toggleFamilyMember(member.id)"
                                >
                                    <div
                                        :class="[
                                            'flex h-4 w-4 shrink-0 items-center justify-center rounded-[4px] border shadow-xs',
                                            familyMemberIds.includes(member.id)
                                                ? 'bg-primary border-primary text-primary-foreground'
                                                : 'border-input',
                                        ]"
                                    >
                                        <Check v-if="familyMemberIds.includes(member.id)" class="h-3 w-3" />
                                    </div>
                                    <div :class="['w-2.5 h-2.5 rounded-full shrink-0', EVENT_COLORS[member.color]?.dot ?? 'bg-blue-500']" />
                                    <span class="text-sm">{{ member.nickname ?? member.name }}</span>
                                </div>
                            </div>
                            <p v-if="errors.family_member_ids" class="text-sm text-destructive">{{ errors.family_member_ids[0] }}</p>
                        </div>
                    </template>

                    <!-- ==================== BILL FIELDS ==================== -->
                    <template v-else-if="entryType === 'bill'">
                        <div class="space-y-2">
                            <Label for="bill-name">Name</Label>
                            <Input id="bill-name" v-model="budgetName" placeholder="e.g. Rent, Netflix" required />
                            <p v-if="errors.name" class="text-sm text-destructive">{{ errors.name[0] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="bill-amount">Amount</Label>
                            <Input id="bill-amount" v-model="budgetAmount" type="number" step="0.01" min="0" placeholder="0.00" required />
                            <p v-if="errors.amount" class="text-sm text-destructive">{{ errors.amount[0] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label>Category</Label>
                            <Select v-model="budgetCategoryId">
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
                            <Label for="bill-start-date">Start Date</Label>
                            <Input id="bill-start-date" v-model="billStartDate" type="date" required />
                            <p v-if="errors.start_date" class="text-sm text-destructive">{{ errors.start_date[0] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label>Frequency</Label>
                            <Select v-model="billFrequency">
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
                            <Label for="bill-notes">Notes</Label>
                            <Textarea id="bill-notes" v-model="budgetNotes" placeholder="Optional notes" />
                        </div>

                        <div class="flex items-center gap-2">
                            <Checkbox id="bill-active" :model-value="billIsActive" @update:model-value="billIsActive = $event as boolean" />
                            <Label for="bill-active" class="cursor-pointer">Active</Label>
                        </div>
                    </template>

                    <!-- ==================== EXPENSE FIELDS ==================== -->
                    <template v-else-if="entryType === 'expense'">
                        <div class="space-y-2">
                            <Label for="expense-name">Name</Label>
                            <Input id="expense-name" v-model="budgetName" placeholder="e.g. Groceries" required />
                            <p v-if="errors.name" class="text-sm text-destructive">{{ errors.name[0] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="expense-amount">Amount</Label>
                            <Input id="expense-amount" v-model="budgetAmount" type="number" step="0.01" min="0" placeholder="0.00" required />
                            <p v-if="errors.amount" class="text-sm text-destructive">{{ errors.amount[0] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label>Category</Label>
                            <Select v-model="budgetCategoryId">
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
                            <Input id="expense-date" v-model="expenseDate" type="date" required />
                            <p v-if="errors.date" class="text-sm text-destructive">{{ errors.date[0] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="expense-notes">Notes</Label>
                            <Textarea id="expense-notes" v-model="budgetNotes" placeholder="Optional notes" />
                        </div>
                    </template>

                    <!-- ==================== INCOME FIELDS ==================== -->
                    <template v-else-if="entryType === 'income'">
                        <div class="space-y-2">
                            <Label for="income-name">Name</Label>
                            <Input id="income-name" v-model="budgetName" placeholder="e.g. Salary, Freelance" required />
                            <p v-if="errors.name" class="text-sm text-destructive">{{ errors.name[0] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="income-amount">Amount</Label>
                            <Input id="income-amount" v-model="budgetAmount" type="number" step="0.01" min="0" placeholder="0.00" required />
                            <p v-if="errors.amount" class="text-sm text-destructive">{{ errors.amount[0] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="income-start-date">Start Date</Label>
                            <Input id="income-start-date" v-model="incomeStartDate" type="date" required />
                            <p v-if="errors.start_date" class="text-sm text-destructive">{{ errors.start_date[0] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label>Frequency</Label>
                            <Select v-model="incomeFrequency">
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
                            <Textarea id="income-notes" v-model="budgetNotes" placeholder="Optional notes" />
                        </div>

                        <div class="flex items-center gap-2">
                            <Checkbox id="income-active" :model-value="incomeIsActive" @update:model-value="incomeIsActive = $event as boolean" />
                            <Label for="income-active" class="cursor-pointer">Active</Label>
                        </div>
                    </template>
                </div>

                <SheetFooter>
                    <div class="flex gap-2">
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
