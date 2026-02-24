<script setup lang="ts">
import { Check } from 'lucide-vue-next';
import { ref, computed, watch } from 'vue';
import { Button } from '@/components/ui/button';
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
import type { FamilyMember } from '@/types/calendar';
import type { FamilyList, ListType, ListVisibility } from '@/types/lists';
import { LIST_TYPE_LABELS, LIST_VISIBILITY_LABELS } from '@/types/lists';

const props = defineProps<{
    list: FamilyList | null;
    familyMembers: FamilyMember[];
    open: boolean;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    saved: [];
}>();

const isEditing = computed(() => !!props.list);
const isSaving = ref(false);
const errors = ref<Record<string, string[]>>({});

const name = ref('');
const type = ref<ListType>('grocery');
const visibility = ref<ListVisibility>('everyone');
const selectedMemberIds = ref<number[]>([]);

watch(() => props.open, (open) => {
    if (!open) return;
    errors.value = {};

    if (props.list) {
        name.value = props.list.name;
        type.value = props.list.type;
        visibility.value = props.list.visibility;
        selectedMemberIds.value = props.list.members?.map(m => m.id) ?? [];
    } else {
        name.value = '';
        type.value = 'grocery';
        visibility.value = 'everyone';
        selectedMemberIds.value = [];
    }
});

function toggleMember(memberId: number) {
    if (selectedMemberIds.value.includes(memberId)) {
        selectedMemberIds.value = selectedMemberIds.value.filter(i => i !== memberId);
    } else {
        selectedMemberIds.value = [...selectedMemberIds.value, memberId];
    }
}

async function save() {
    isSaving.value = true;
    errors.value = {};

    const body: Record<string, unknown> = {
        name: name.value,
        type: type.value,
        visibility: visibility.value,
    };

    if (visibility.value === 'specific') {
        body.member_ids = selectedMemberIds.value;
    }

    try {
        const url = isEditing.value ? `/lists/${props.list!.id}` : '/lists';
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
                <SheetTitle>{{ isEditing ? 'Edit List' : 'New List' }}</SheetTitle>
            </SheetHeader>

            <form @submit.prevent="save" class="flex flex-1 flex-col">
                <div class="space-y-4 px-4 flex-1">
                    <div class="space-y-2">
                        <Label for="list-name">Name</Label>
                        <Input id="list-name" v-model="name" placeholder="e.g. Weekly Groceries" required />
                        <p v-if="errors.name" class="text-sm text-destructive">{{ errors.name[0] }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label for="list-type">Type</Label>
                        <Select v-model="type">
                            <SelectTrigger id="list-type">
                                <SelectValue placeholder="Select type" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="(label, value) in LIST_TYPE_LABELS"
                                    :key="value"
                                    :value="value"
                                >
                                    {{ label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="errors.type" class="text-sm text-destructive">{{ errors.type[0] }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label for="list-visibility">Visibility</Label>
                        <Select v-model="visibility">
                            <SelectTrigger id="list-visibility">
                                <SelectValue placeholder="Select visibility" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="(label, value) in LIST_VISIBILITY_LABELS"
                                    :key="value"
                                    :value="value"
                                >
                                    {{ label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="errors.visibility" class="text-sm text-destructive">{{ errors.visibility[0] }}</p>
                    </div>

                    <div v-if="visibility === 'specific'" class="space-y-2">
                        <Label>Members</Label>
                        <div v-if="familyMembers.length === 0" class="text-sm text-muted-foreground">
                            No family members available.
                        </div>
                        <div v-else class="space-y-2">
                            <div
                                v-for="member in familyMembers"
                                :key="member.id"
                                class="flex items-center gap-2 cursor-pointer"
                                @click="toggleMember(member.id)"
                            >
                                <div
                                    :class="[
                                        'flex h-4 w-4 shrink-0 items-center justify-center rounded-[4px] border shadow-xs',
                                        selectedMemberIds.includes(member.id)
                                            ? 'bg-primary border-primary text-primary-foreground'
                                            : 'border-input',
                                    ]"
                                >
                                    <Check v-if="selectedMemberIds.includes(member.id)" class="h-3 w-3" />
                                </div>
                                <span class="text-sm">
                                    {{ member.nickname || member.name }}
                                </span>
                            </div>
                        </div>
                        <p v-if="errors.member_ids" class="text-sm text-destructive">{{ errors.member_ids[0] }}</p>
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
