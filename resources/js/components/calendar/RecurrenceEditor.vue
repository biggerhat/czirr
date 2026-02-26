<script setup lang="ts">
import { computed } from 'vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { RecurrenceConfig, RecurrenceFrequency, RecurrenceEndType } from '@/lib/recurrence';

const model = defineModel<RecurrenceConfig>({ required: true });

const frequencyOptions: { value: RecurrenceFrequency; label: string }[] = [
    { value: 'none', label: 'Does not repeat' },
    { value: 'daily', label: 'Daily' },
    { value: 'weekday', label: 'Every weekday (Mon–Fri)' },
    { value: 'weekly', label: 'Weekly' },
    { value: 'biweekly', label: 'Bi-weekly' },
    { value: 'monthly', label: 'Monthly' },
    { value: 'yearly', label: 'Yearly' },
];

const endTypeOptions: { value: RecurrenceEndType; label: string }[] = [
    { value: 'never', label: 'Never' },
    { value: 'until', label: 'On date' },
    { value: 'count', label: 'After N occurrences' },
];

const dayLabels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

const unitLabel = computed(() => {
    switch (model.value.frequency) {
        case 'daily': return 'day(s)';
        case 'weekly': return 'week(s)';
        case 'monthly': return 'month(s)';
        case 'yearly': return 'year(s)';
        default: return '';
    }
});

function setFrequency(value: string | number | bigint | Record<string, any> | null) {
    model.value = { ...model.value, frequency: String(value) as RecurrenceFrequency };
}

function setInterval(e: Event) {
    const val = parseInt((e.target as HTMLInputElement).value) || 1;
    model.value = { ...model.value, interval: Math.max(1, val) };
}

function toggleDay(dayIndex: number) {
    const days = [...model.value.byWeekday];
    const idx = days.indexOf(dayIndex);
    if (idx >= 0) {
        days.splice(idx, 1);
    } else {
        days.push(dayIndex);
    }
    model.value = { ...model.value, byWeekday: days };
}

function setEndType(value: string | number | bigint | Record<string, any> | null) {
    model.value = { ...model.value, endType: String(value) as RecurrenceEndType };
}

function setUntilDate(e: Event) {
    model.value = { ...model.value, untilDate: (e.target as HTMLInputElement).value };
}

function setCount(e: Event) {
    const val = parseInt((e.target as HTMLInputElement).value) || 1;
    model.value = { ...model.value, count: Math.max(1, val) };
}
</script>

<template>
    <div class="space-y-3">
        <div class="space-y-2">
            <Label>Repeat</Label>
            <Select :model-value="model.frequency" @update:model-value="setFrequency">
                <SelectTrigger>
                    <SelectValue placeholder="Select frequency" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem v-for="opt in frequencyOptions" :key="opt.value" :value="opt.value">
                        {{ opt.label }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>

        <template v-if="model.frequency !== 'none'">
            <!-- Interval (not shown for weekday/biweekly presets) -->
            <div v-if="model.frequency !== 'weekday' && model.frequency !== 'biweekly'" class="space-y-2">
                <Label>Every</Label>
                <div class="flex items-center gap-2">
                    <Input
                        type="number"
                        min="1"
                        max="99"
                        :value="model.interval"
                        @input="setInterval"
                        class="w-20"
                    />
                    <span class="text-sm text-muted-foreground">{{ unitLabel }}</span>
                </div>
            </div>

            <!-- Day-of-week (weekly/biweekly, not shown for weekday preset) -->
            <div v-if="model.frequency === 'weekly' || model.frequency === 'biweekly'" class="space-y-2">
                <Label>On days</Label>
                <div class="flex gap-1">
                    <button
                        v-for="(label, index) in dayLabels"
                        :key="index"
                        type="button"
                        :class="[
                            'h-8 w-10 rounded-md text-xs font-medium transition-colors',
                            model.byWeekday.includes(index)
                                ? 'bg-primary text-primary-foreground'
                                : 'bg-muted text-muted-foreground hover:bg-muted/80',
                        ]"
                        @click="toggleDay(index)"
                    >
                        {{ label }}
                    </button>
                </div>
            </div>

            <!-- End condition -->
            <div class="space-y-2">
                <Label>Ends</Label>
                <Select :model-value="model.endType" @update:model-value="setEndType">
                    <SelectTrigger>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="opt in endTypeOptions" :key="opt.value" :value="opt.value">
                            {{ opt.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <Input
                    v-if="model.endType === 'until'"
                    type="date"
                    :value="model.untilDate ?? ''"
                    @input="setUntilDate"
                />

                <div v-if="model.endType === 'count'" class="flex items-center gap-2">
                    <Input
                        type="number"
                        min="1"
                        max="999"
                        :value="model.count ?? 1"
                        @input="setCount"
                        class="w-24"
                    />
                    <span class="text-sm text-muted-foreground">occurrences</span>
                </div>
            </div>
        </template>
    </div>
</template>
