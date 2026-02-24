<script setup lang="ts">
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import type { EditMode } from '@/types/calendar';

defineProps<{
    open: boolean;
    action: 'edit' | 'delete';
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    confirm: [mode: EditMode];
    cancel: [];
}>();

const selectedMode = ref<EditMode>('single');

function handleConfirm() {
    emit('confirm', selectedMode.value);
}

function handleCancel() {
    emit('cancel');
    emit('update:open', false);
}
</script>

<template>
    <Dialog :open="open" @update:open="$emit('update:open', $event)">
        <DialogContent :show-close-button="false">
            <DialogHeader>
                <DialogTitle>
                    {{ action === 'edit' ? 'Edit Recurring Event' : 'Delete Recurring Event' }}
                </DialogTitle>
                <DialogDescription>
                    {{ action === 'edit'
                        ? 'How would you like to edit this recurring event?'
                        : 'How would you like to delete this recurring event?'
                    }}
                </DialogDescription>
            </DialogHeader>

            <RadioGroup v-model="selectedMode" class="py-2">
                <div class="flex items-center gap-2">
                    <RadioGroupItem id="mode-single" value="single" />
                    <Label for="mode-single" class="cursor-pointer">This event only</Label>
                </div>
                <div class="flex items-center gap-2">
                    <RadioGroupItem id="mode-future" value="future" />
                    <Label for="mode-future" class="cursor-pointer">This and following events</Label>
                </div>
                <div class="flex items-center gap-2">
                    <RadioGroupItem id="mode-all" value="all" />
                    <Label for="mode-all" class="cursor-pointer">All events</Label>
                </div>
            </RadioGroup>

            <DialogFooter>
                <Button variant="outline" @click="handleCancel">
                    Cancel
                </Button>
                <Button
                    :variant="action === 'delete' ? 'destructive' : 'default'"
                    @click="handleConfirm"
                >
                    {{ action === 'edit' ? 'Edit' : 'Delete' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
