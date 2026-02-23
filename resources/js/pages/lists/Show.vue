<script setup lang="ts">
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import ListModal from '@/components/lists/ListModal.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Check, Pencil, Plus, Trash2, X } from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';
import type { FamilyMember } from '@/types/calendar';
import type { FamilyList, FamilyListItem } from '@/types/lists';
import { LIST_TYPE_LABELS } from '@/types/lists';

const props = defineProps<{
    list: FamilyList;
    familyMembers: FamilyMember[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Lists', href: '/lists' },
    { title: props.list.name },
];

const items = computed(() => props.list.items ?? []);
const completedCount = computed(() => items.value.filter(i => i.is_completed).length);
const totalCount = computed(() => items.value.length);

// Inline add
const newItemName = ref('');
const newItemQty = ref('');
const isAdding = ref(false);

// Inline edit
const editingItemId = ref<number | null>(null);
const editName = ref('');
const editQty = ref('');
const editNotes = ref('');

// List edit modal
const showListModal = ref(false);

function xsrfHeaders(): HeadersInit {
    return {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': decodeURIComponent(
            document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
        ),
    };
}

async function addItem() {
    if (!newItemName.value.trim()) return;
    isAdding.value = true;

    await fetch(`/lists/${props.list.id}/items`, {
        method: 'POST',
        headers: xsrfHeaders(),
        body: JSON.stringify({
            name: newItemName.value.trim(),
            quantity: newItemQty.value.trim() || null,
        }),
    });

    newItemName.value = '';
    newItemQty.value = '';
    isAdding.value = false;
    router.reload();
}

async function toggleItem(item: FamilyListItem) {
    await fetch(`/list-items/${item.id}/toggle`, {
        method: 'PATCH',
        headers: xsrfHeaders(),
    });
    router.reload();
}

function startEdit(item: FamilyListItem) {
    editingItemId.value = item.id;
    editName.value = item.name;
    editQty.value = item.quantity ?? '';
    editNotes.value = item.notes ?? '';
}

function cancelEdit() {
    editingItemId.value = null;
}

async function saveEdit(item: FamilyListItem) {
    await fetch(`/list-items/${item.id}`, {
        method: 'PUT',
        headers: xsrfHeaders(),
        body: JSON.stringify({
            name: editName.value.trim(),
            quantity: editQty.value.trim() || null,
            notes: editNotes.value.trim() || null,
        }),
    });
    editingItemId.value = null;
    router.reload();
}

const showClearDialog = ref(false);
const isClearing = ref(false);

async function clearCompleted() {
    isClearing.value = true;
    await fetch(`/lists/${props.list.id}/items/completed`, {
        method: 'DELETE',
        headers: xsrfHeaders(),
    });
    isClearing.value = false;
    showClearDialog.value = false;
    router.reload();
}

async function deleteItem(item: FamilyListItem) {
    await fetch(`/list-items/${item.id}`, {
        method: 'DELETE',
        headers: xsrfHeaders(),
    });
    router.reload();
}

function onListSaved() {
    router.reload();
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <h2 class="text-lg font-semibold">{{ list.name }}</h2>
                    <Badge variant="secondary">{{ LIST_TYPE_LABELS[list.type] }}</Badge>
                </div>
                <div class="flex items-center gap-2 text-sm text-muted-foreground">
                    <span v-if="totalCount > 0">{{ completedCount }}/{{ totalCount }} done</span>
                    <Button v-if="completedCount > 0" size="sm" variant="outline" @click="showClearDialog = true">
                        Clear Checked
                    </Button>
                    <Button size="sm" variant="outline" @click="showListModal = true">
                        <Pencil class="h-3.5 w-3.5 mr-1" />
                        Edit List
                    </Button>
                </div>
            </div>

            <!-- Progress bar -->
            <div v-if="totalCount > 0" class="h-2 rounded-full bg-muted overflow-hidden">
                <div
                    class="h-full bg-primary transition-all duration-300"
                    :style="{ width: `${(completedCount / totalCount) * 100}%` }"
                />
            </div>

            <!-- Items -->
            <div class="rounded-lg border">
                <!-- Empty state -->
                <div v-if="items.length === 0 && !newItemName" class="p-8 text-center text-muted-foreground">
                    No items yet. Add your first item below.
                </div>

                <!-- Item list -->
                <div v-if="items.length > 0" class="divide-y">
                    <div
                        v-for="item in items"
                        :key="item.id"
                        class="flex items-center gap-3 px-4 py-3 cursor-pointer"
                        @click="editingItemId !== item.id && toggleItem(item)"
                    >
                        <!-- Normal display mode -->
                        <template v-if="editingItemId !== item.id">
                            <Checkbox
                                :model-value="item.is_completed"
                                @click.stop
                                @update:model-value="toggleItem(item)"
                            />
                            <div class="flex-1 min-w-0">
                                <span
                                    class="text-sm"
                                    :style="item.is_completed ? { textDecoration: 'line-through', color: 'var(--color-muted-foreground)' } : {}"
                                >
                                    {{ item.name }}<span v-if="item.quantity" class="text-muted-foreground"> ({{ item.quantity }})</span>
                                </span>
                                <p
                                    v-if="item.notes"
                                    class="text-xs text-muted-foreground mt-0.5"
                                    :style="item.is_completed ? { textDecoration: 'line-through' } : {}"
                                >
                                    {{ item.notes }}
                                </p>
                            </div>
                            <Button variant="ghost" size="icon" class="h-7 w-7 shrink-0" @click.stop="startEdit(item)">
                                <Pencil class="h-3 w-3" />
                            </Button>
                            <Button variant="ghost" size="icon" class="h-7 w-7 shrink-0 text-destructive" @click.stop="deleteItem(item)">
                                <Trash2 class="h-3 w-3" />
                            </Button>
                        </template>

                        <!-- Inline edit mode -->
                        <template v-else>
                            <div class="flex-1 flex flex-col gap-2">
                                <div class="flex items-center gap-2">
                                    <Input
                                        v-model="editName"
                                        placeholder="Item name"
                                        class="h-8 text-sm"
                                        @keydown.enter="saveEdit(item)"
                                        @keydown.escape="cancelEdit"
                                    />
                                    <Input
                                        v-model="editQty"
                                        placeholder="Qty"
                                        class="h-8 text-sm w-20"
                                        @keydown.enter="saveEdit(item)"
                                        @keydown.escape="cancelEdit"
                                    />
                                </div>
                                <Input
                                    v-model="editNotes"
                                    placeholder="Notes (optional)"
                                    class="h-8 text-sm"
                                    @keydown.enter="saveEdit(item)"
                                    @keydown.escape="cancelEdit"
                                />
                            </div>
                            <Button variant="ghost" size="icon" class="h-7 w-7 shrink-0" @click="saveEdit(item)">
                                <Check class="h-3 w-3" />
                            </Button>
                            <Button variant="ghost" size="icon" class="h-7 w-7 shrink-0" @click="cancelEdit">
                                <X class="h-3 w-3" />
                            </Button>
                        </template>
                    </div>
                </div>

                <!-- Inline add row -->
                <div class="flex items-center gap-3 px-4 py-3 border-t">
                    <Plus class="h-4 w-4 text-muted-foreground shrink-0" />
                    <Input
                        v-model="newItemName"
                        placeholder="Add item..."
                        class="h-8 text-sm flex-1"
                        @keydown.enter="addItem"
                    />
                    <Input
                        v-model="newItemQty"
                        placeholder="Qty"
                        class="h-8 text-sm w-20"
                        @keydown.enter="addItem"
                    />
                    <Button size="sm" :disabled="!newItemName.trim() || isAdding" @click="addItem">
                        Add
                    </Button>
                </div>
            </div>

            <!-- Clear checked dialog -->
            <Dialog :open="showClearDialog" @update:open="showClearDialog = $event">
                <DialogContent class="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Clear checked items?</DialogTitle>
                        <DialogDescription>
                            This will remove {{ completedCount }} checked {{ completedCount === 1 ? 'item' : 'items' }} from the list. This cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter class="gap-2">
                        <Button variant="outline" @click="showClearDialog = false">Cancel</Button>
                        <Button variant="destructive" :disabled="isClearing" @click="clearCompleted">
                            {{ isClearing ? 'Clearing...' : 'Clear Items' }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- List edit modal -->
            <ListModal
                :list="list"
                :family-members="familyMembers"
                :open="showListModal"
                @update:open="showListModal = $event"
                @saved="onListSaved"
            />
        </div>
    </AppLayout>
</template>
