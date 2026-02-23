<script setup lang="ts">
import { computed } from 'vue';
import { Check, Plus } from 'lucide-vue-next';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { EVENT_COLORS } from '@/lib/calendar';
import type { FamilyMember } from '@/types/calendar';
import type { ChoreAssignment, DayOfWeek } from '@/types/chores';

const props = defineProps<{
    assignments: ChoreAssignment[];
    familyMembers: FamilyMember[];
    choreId: number;
    day: DayOfWeek;
    readonly?: boolean;
}>();

const emit = defineEmits<{
    toggle: [familyMemberId: number];
}>();

const assignedIds = computed(() =>
    new Set(props.assignments.map(a => a.family_member_id)),
);

function isAssigned(memberId: number): boolean {
    return assignedIds.value.has(memberId);
}

function getDotClass(member: FamilyMember): string {
    return EVENT_COLORS[member.color]?.dot ?? 'bg-blue-500';
}
</script>

<template>
    <!-- Read-only: just show dots -->
    <div v-if="readonly" class="flex min-h-[2.25rem] w-full items-center justify-center gap-1 px-1 py-1">
        <span
            v-for="a in assignments"
            :key="a.id"
            class="h-3 w-3 shrink-0 rounded-full"
            :class="getDotClass(a.family_member)"
            :title="a.family_member.nickname || a.family_member.name"
        />
    </div>

    <!-- Editable: popover picker -->
    <Popover v-else>
        <PopoverTrigger as-child>
            <button
                class="flex min-h-[2.25rem] w-full items-center justify-center gap-1 rounded-md px-1 py-1 transition-colors hover:bg-accent"
            >
                <template v-if="assignments.length > 0">
                    <span
                        v-for="a in assignments"
                        :key="a.id"
                        class="h-3 w-3 shrink-0 rounded-full"
                        :class="getDotClass(a.family_member)"
                        :title="a.family_member.nickname || a.family_member.name"
                    />
                </template>
                <Plus v-else class="h-4 w-4 text-muted-foreground/50" />
            </button>
        </PopoverTrigger>
        <PopoverContent class="w-48 p-1" align="center">
            <button
                v-for="member in familyMembers"
                :key="member.id"
                class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-sm transition-colors hover:bg-accent"
                @click="emit('toggle', member.id)"
            >
                <span
                    class="h-3 w-3 shrink-0 rounded-full"
                    :class="getDotClass(member)"
                />
                <span class="flex-1 text-left truncate">{{ member.nickname || member.name }}</span>
                <Check v-if="isAssigned(member.id)" class="h-3.5 w-3.5 shrink-0 text-emerald-500" />
            </button>
            <p v-if="familyMembers.length === 0" class="px-2 py-1.5 text-sm text-muted-foreground">
                No family members
            </p>
        </PopoverContent>
    </Popover>
</template>
