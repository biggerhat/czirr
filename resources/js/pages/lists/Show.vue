<script setup lang="ts">
import { router, usePage, usePoll } from '@inertiajs/vue3';
import { Check, Pencil, Pin, PinOff, Plus, Trash2, X } from 'lucide-vue-next';
import { ref, computed, watch } from 'vue';
import ListModal from '@/components/lists/ListModal.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { FamilyMember } from '@/types/calendar';
import type { FamilyList, FamilyListItem } from '@/types/lists';
import { AISLES, LIST_TYPE_LABELS } from '@/types/lists';

// Flash data only arrives on the first response, so capture it once (the poll replaces props).
const notice = ref<string | null>(
    (usePage().props.flash as { status?: string } | undefined)?.status ?? null,
);

const props = defineProps<{
    list: FamilyList;
    familyMembers: FamilyMember[];
    suggestions?: string[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Lists', href: '/lists' },
    { title: props.list.name },
];

// Live sync: re-fetch just the list every few seconds (pauses in background tabs).
// Realtime (websocket) refreshes this page; poll only when it isn't configured.
usePoll(5000, { only: ['list'] }, { autoStart: !usePage().props.realtime });

// Local copy so checking items feels instant, even on slow store wifi.
const items = ref<FamilyListItem[]>([...(props.list.items ?? [])]);
watch(
    () => props.list.items,
    (v) => (items.value = [...(v ?? [])]),
);

const isShopping = computed(
    () => props.list.type === 'grocery' || props.list.type === 'shopping',
);

const sections = computed(() => {
    if (!isShopping.value) {
        return [{ title: '', items: items.value }];
    }
    const open = items.value.filter((i) => !i.is_completed);
    const byAisle = new Map<string, FamilyListItem[]>();
    for (const item of open) {
        const key = item.category || 'Other';
        byAisle.set(key, [...(byAisle.get(key) ?? []), item]);
    }
    const known = AISLES.filter((a) => byAisle.has(a));
    const custom = [...byAisle.keys()].filter(
        (k) => !(AISLES as readonly string[]).includes(k),
    );
    const result = [...custom, ...known].map((title) => ({
        title,
        items: byAisle.get(title)!,
    }));
    const done = items.value.filter((i) => i.is_completed);
    if (done.length) result.push({ title: 'In cart', items: done });

    return result;
});
const completedCount = computed(
    () => items.value.filter((i) => i.is_completed).length,
);
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
const editCategory = ref('');

// List edit modal
const showListModal = ref(false);

function xsrfHeaders(): HeadersInit {
    return {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': decodeURIComponent(
            document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
        ),
    };
}

async function addItem(quickName?: string) {
    const name = (
        typeof quickName === 'string' ? quickName : newItemName.value
    ).trim();
    if (!name) return;
    isAdding.value = true;

    await fetch(`/lists/${props.list.id}/items`, {
        method: 'POST',
        headers: xsrfHeaders(),
        body: JSON.stringify({
            name,
            quantity:
                typeof quickName === 'string'
                    ? null
                    : newItemQty.value.trim() || null,
        }),
    });

    if (typeof quickName !== 'string') {
        newItemName.value = '';
        newItemQty.value = '';
    }
    isAdding.value = false;
    router.reload();
}

async function toggleItem(item: FamilyListItem) {
    item.is_completed = !item.is_completed; // optimistic
    const res = await fetch(`/list-items/${item.id}/toggle`, {
        method: 'PATCH',
        headers: xsrfHeaders(),
    });
    if (!res.ok) item.is_completed = !item.is_completed;
    router.reload({ only: ['list'] });
}

function startEdit(item: FamilyListItem) {
    editingItemId.value = item.id;
    editName.value = item.name;
    editQty.value = item.quantity ?? '';
    editNotes.value = item.notes ?? '';
    editCategory.value = item.category ?? '';
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
            category: editCategory.value || null,
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

async function togglePin() {
    await fetch(`/lists/${props.list.id}/pin`, {
        method: 'PATCH',
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
            <!-- One-off note from the server (e.g. pantry staples left off a generated list) -->
            <div
                v-if="notice"
                class="flex items-start gap-2 rounded-md border border-emerald-500/40 bg-emerald-50 p-3 text-sm dark:bg-emerald-950/30"
                role="status"
            >
                <span class="flex-1">{{ notice }}</span>
                <button
                    type="button"
                    class="rounded p-0.5 hover:bg-background"
                    aria-label="Dismiss"
                    @click="notice = null"
                >
                    <X class="h-4 w-4" />
                </button>
            </div>
            <!-- Header -->
            <div
                class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex items-center gap-3">
                    <h2 class="text-lg font-semibold">{{ list.name }}</h2>
                    <Badge variant="secondary">{{
                        LIST_TYPE_LABELS[list.type]
                    }}</Badge>
                </div>
                <div
                    class="flex items-center gap-2 text-sm text-muted-foreground"
                >
                    <span v-if="totalCount > 0"
                        >{{ completedCount }}/{{ totalCount }} done</span
                    >
                    <Button
                        v-if="completedCount > 0"
                        size="sm"
                        variant="outline"
                        @click="showClearDialog = true"
                    >
                        Clear Checked
                    </Button>
                    <Button size="sm" variant="outline" @click="togglePin">
                        <Pin v-if="!list.is_pinned" class="mr-1 h-3.5 w-3.5" />
                        <PinOff v-else class="mr-1 h-3.5 w-3.5" />
                        {{ list.is_pinned ? 'Unpin' : 'Pin' }}
                    </Button>
                    <Button
                        size="sm"
                        variant="outline"
                        @click="showListModal = true"
                    >
                        <Pencil class="mr-1 h-3.5 w-3.5" />
                        Edit List
                    </Button>
                </div>
            </div>

            <!-- Progress bar -->
            <div
                v-if="totalCount > 0"
                class="h-2 overflow-hidden rounded-full bg-muted"
            >
                <div
                    class="h-full bg-primary transition-all duration-300"
                    :style="{
                        width: `${(completedCount / totalCount) * 100}%`,
                    }"
                />
            </div>

            <!-- Items -->
            <div class="rounded-lg border">
                <!-- Empty state -->
                <div
                    v-if="items.length === 0 && !newItemName"
                    class="p-8 text-center text-muted-foreground"
                >
                    No items yet. Add your first item below.
                </div>

                <!-- Item list -->
                <div v-if="items.length > 0">
                    <div v-for="section in sections" :key="section.title">
                        <div
                            v-if="section.title"
                            class="bg-muted/50 px-4 py-1.5 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                        >
                            {{ section.title }}
                            <span class="font-normal"
                                >({{ section.items.length }})</span
                            >
                        </div>
                        <div class="divide-y">
                            <div
                                v-for="item in section.items"
                                :key="item.id"
                                class="flex cursor-pointer items-center gap-3 px-4 py-3"
                                @click="
                                    editingItemId !== item.id &&
                                    toggleItem(item)
                                "
                            >
                                <!-- Normal display mode -->
                                <template v-if="editingItemId !== item.id">
                                    <Checkbox
                                        class="size-5"
                                        :aria-label="`Mark ${item.name} ${item.is_completed ? 'not done' : 'done'}`"
                                        :model-value="item.is_completed"
                                        @click.stop
                                        @update:model-value="toggleItem(item)"
                                    />
                                    <div class="min-w-0 flex-1">
                                        <span
                                            class="text-sm"
                                            :style="
                                                item.is_completed
                                                    ? {
                                                          textDecoration:
                                                              'line-through',
                                                          color: 'var(--color-muted-foreground)',
                                                      }
                                                    : {}
                                            "
                                        >
                                            {{ item.name
                                            }}<span
                                                v-if="item.quantity"
                                                class="text-muted-foreground"
                                            >
                                                ({{ item.quantity }})</span
                                            >
                                        </span>
                                        <p
                                            v-if="item.notes"
                                            class="mt-0.5 text-xs text-muted-foreground"
                                            :style="
                                                item.is_completed
                                                    ? {
                                                          textDecoration:
                                                              'line-through',
                                                      }
                                                    : {}
                                            "
                                        >
                                            {{ item.notes }}
                                        </p>
                                    </div>
                                    <Button
                                        aria-label="Edit"
                                        variant="ghost"
                                        size="icon"
                                        class="h-8 w-8 shrink-0"
                                        @click.stop="startEdit(item)"
                                    >
                                        <Pencil class="h-3 w-3" />
                                    </Button>
                                    <Button
                                        aria-label="Delete item"
                                        variant="ghost"
                                        size="icon"
                                        class="h-8 w-8 shrink-0 text-muted-foreground hover:text-destructive"
                                        @click.stop="deleteItem(item)"
                                    >
                                        <Trash2 class="h-3 w-3" />
                                    </Button>
                                </template>

                                <!-- Inline edit mode -->
                                <template v-else>
                                    <div class="flex flex-1 flex-col gap-2">
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
                                                class="h-8 w-16 text-sm sm:w-20"
                                                @keydown.enter="saveEdit(item)"
                                                @keydown.escape="cancelEdit"
                                            />
                                        </div>
                                        <select
                                            v-if="isShopping"
                                            v-model="editCategory"
                                            class="h-8 rounded-md border bg-background px-2 text-sm"
                                        >
                                            <option value="">
                                                Aisle: auto
                                            </option>
                                            <option
                                                v-for="a in AISLES"
                                                :key="a"
                                                :value="a"
                                            >
                                                {{ a }}
                                            </option>
                                        </select>
                                        <Input
                                            v-model="editNotes"
                                            placeholder="Notes (optional)"
                                            class="h-8 text-sm"
                                            @keydown.enter="saveEdit(item)"
                                            @keydown.escape="cancelEdit"
                                        />
                                    </div>
                                    <Button
                                        aria-label="Save"
                                        variant="ghost"
                                        size="icon"
                                        class="h-8 w-8 shrink-0"
                                        @click="saveEdit(item)"
                                    >
                                        <Check class="h-3 w-3" />
                                    </Button>
                                    <Button
                                        aria-label="Cancel"
                                        variant="ghost"
                                        size="icon"
                                        class="h-8 w-8 shrink-0"
                                        @click="cancelEdit"
                                    >
                                        <X class="h-3 w-3" />
                                    </Button>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick-add suggestions -->
                <div
                    v-if="suggestions?.length"
                    class="flex flex-wrap items-center gap-2 border-t px-4 py-3"
                >
                    <span class="text-xs text-muted-foreground"
                        >Bought before:</span
                    >
                    <button
                        v-for="name in suggestions"
                        :key="name"
                        type="button"
                        class="rounded-full border px-3 py-1 text-xs hover:bg-muted"
                        @click="addItem(name)"
                    >
                        + {{ name }}
                    </button>
                </div>

                <!-- Inline add row -->
                <div class="flex items-center gap-3 border-t px-4 py-3">
                    <Plus class="h-4 w-4 shrink-0 text-muted-foreground" />
                    <Input
                        v-model="newItemName"
                        placeholder="Add item..."
                        class="h-8 flex-1 text-sm"
                        @keydown.enter="addItem()"
                    />
                    <Input
                        v-model="newItemQty"
                        placeholder="Qty"
                        class="h-8 w-16 text-sm sm:w-20"
                        @keydown.enter="addItem()"
                    />
                    <Button
                        size="sm"
                        :disabled="!newItemName.trim() || isAdding"
                        @click="addItem()"
                    >
                        Add
                    </Button>
                </div>
            </div>

            <!-- Clear checked dialog -->
            <Dialog
                :open="showClearDialog"
                @update:open="showClearDialog = $event"
            >
                <DialogContent class="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Clear checked items?</DialogTitle>
                        <DialogDescription>
                            This will remove {{ completedCount }} checked
                            {{ completedCount === 1 ? 'item' : 'items' }} from
                            the list. This cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter class="gap-2">
                        <Button
                            variant="outline"
                            @click="showClearDialog = false"
                            >Cancel</Button
                        >
                        <Button
                            variant="destructive"
                            :disabled="isClearing"
                            @click="clearCompleted"
                        >
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
