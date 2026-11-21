<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import type { Chore } from '@/types/chores';

const props = defineProps<{
    chore: Chore | null;
    open: boolean;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    saved: [];
}>();

const isEditing = computed(() => !!props.chore);
const isSaving = ref(false);
const errors = ref<Record<string, string[]>>({});

const name = ref('');
const description = ref('');
const isActive = ref(true);
const points = ref(10);
const requiresApproval = ref(false);

watch(
    () => props.open,
    (open) => {
        if (!open) return;
        errors.value = {};

        if (props.chore) {
            name.value = props.chore.name;
            description.value = props.chore.description ?? '';
            isActive.value = props.chore.is_active;
            points.value = props.chore.points;
            requiresApproval.value = props.chore.requires_approval ?? false;
        } else {
            name.value = '';
            description.value = '';
            isActive.value = true;
            points.value = 10;
            requiresApproval.value = false;
        }
    },
);

async function save() {
    isSaving.value = true;
    errors.value = {};

    const body = {
        name: name.value,
        description: description.value || null,
        is_active: isActive.value,
        points: points.value,
        requires_approval: requiresApproval.value,
    };

    try {
        const url = isEditing.value ? `/chores/${props.chore!.id}` : '/chores';
        const method = isEditing.value ? 'PUT' : 'POST';

        const response = await fetch(url, {
            method,
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
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
            errors.value = {
                name: [`Save failed (${response.status}). Please try again.`],
            };
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
                <SheetTitle>{{
                    isEditing ? 'Edit Chore' : 'New Chore'
                }}</SheetTitle>
            </SheetHeader>

            <form @submit.prevent="save" class="flex flex-1 flex-col">
                <div class="flex-1 space-y-4 px-4">
                    <div class="space-y-2">
                        <Label for="chore-name">Name</Label>
                        <Input
                            id="chore-name"
                            v-model="name"
                            placeholder="e.g. Take out trash"
                            required
                        />
                        <p v-if="errors.name" class="text-sm text-destructive">
                            {{ errors.name[0] }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="chore-description">Description</Label>
                        <Textarea
                            id="chore-description"
                            v-model="description"
                            placeholder="Optional details"
                        />
                        <p
                            v-if="errors.description"
                            class="text-sm text-destructive"
                        >
                            {{ errors.description[0] }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="chore-points">Points</Label>
                        <Input
                            id="chore-points"
                            v-model.number="points"
                            type="number"
                            min="1"
                            max="1000"
                        />
                        <p
                            v-if="errors.points"
                            class="text-sm text-destructive"
                        >
                            {{ errors.points[0] }}
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <Checkbox
                            id="chore-active"
                            :model-value="isActive"
                            @update:model-value="isActive = $event as boolean"
                        />
                        <Label for="chore-active" class="cursor-pointer"
                            >Active</Label
                        >
                    </div>

                    <div class="flex items-start gap-2">
                        <Checkbox
                            id="chore-approval"
                            class="mt-0.5"
                            :model-value="requiresApproval"
                            @update:model-value="
                                requiresApproval = $event as boolean
                            "
                        />
                        <div>
                            <Label for="chore-approval" class="cursor-pointer"
                                >Needs parent approval</Label
                            >
                            <p class="text-xs text-muted-foreground">
                                Kids' completions wait for a parent to approve
                                before the points count.
                            </p>
                        </div>
                    </div>
                </div>

                <SheetFooter>
                    <div class="flex w-full gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            class="flex-1"
                            @click="$emit('update:open', false)"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            class="flex-1"
                            :disabled="isSaving"
                        >
                            {{
                                isSaving
                                    ? 'Saving...'
                                    : isEditing
                                      ? 'Update'
                                      : 'Create'
                            }}
                        </Button>
                    </div>
                </SheetFooter>
            </form>
        </SheetContent>
    </Sheet>
</template>
