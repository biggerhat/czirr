<script setup lang="ts">
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import ListModal from '@/components/lists/ListModal.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Pencil, Plus, ShoppingCart, ShoppingBag, CheckSquare, Heart, List, Trash2 } from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';
import type { FamilyMember } from '@/types/calendar';
import type { FamilyList, ListType } from '@/types/lists';
import { LIST_TYPE_LABELS, LIST_VISIBILITY_LABELS } from '@/types/lists';

const props = defineProps<{
    lists: FamilyList[];
    familyMembers: FamilyMember[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Lists' },
];

const typeIcons: Record<ListType, typeof ShoppingCart> = {
    grocery: ShoppingCart,
    shopping: ShoppingBag,
    todo: CheckSquare,
    wishlist: Heart,
    custom: List,
};

const showModal = ref(false);
const editingList = ref<FamilyList | null>(null);

function openCreate() {
    editingList.value = null;
    showModal.value = true;
}

function openEdit(list: FamilyList) {
    editingList.value = list;
    showModal.value = true;
}

function onSaved() {
    router.reload();
}

const showDeleteDialog = ref(false);
const deletingList = ref<FamilyList | null>(null);
const isDeleting = ref(false);

function openDelete(list: FamilyList) {
    deletingList.value = list;
    showDeleteDialog.value = true;
}

async function confirmDelete() {
    if (!deletingList.value) return;
    isDeleting.value = true;

    await fetch(`/lists/${deletingList.value.id}`, {
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
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold">Lists</h2>
                <Button size="sm" @click="openCreate">
                    <Plus class="h-4 w-4 mr-1" />
                    New List
                </Button>
            </div>

            <!-- Empty state -->
            <div v-if="lists.length === 0" class="rounded-lg border border-dashed p-8 text-center text-muted-foreground">
                No lists yet. Click "New List" to get started.
            </div>

            <!-- Card grid -->
            <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="list in lists"
                    :key="list.id"
                    :href="`/lists/${list.id}`"
                    class="rounded-lg border p-4 space-y-3 hover:border-primary/50 transition-colors"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-center gap-2 min-w-0 flex-1">
                            <component
                                :is="typeIcons[list.type]"
                                class="h-4 w-4 shrink-0 text-muted-foreground"
                            />
                            <span class="font-medium truncate">{{ list.name }}</span>
                        </div>
                        <div class="flex items-center gap-1 shrink-0">
                            <Button variant="outline" size="icon" class="h-7 w-7" @click.prevent="openEdit(list)">
                                <Pencil class="h-3.5 w-3.5" />
                            </Button>
                            <Button variant="outline" size="icon" class="h-7 w-7 text-destructive" @click.prevent="openDelete(list)">
                                <Trash2 class="h-3.5 w-3.5" />
                            </Button>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <Badge variant="secondary">{{ LIST_TYPE_LABELS[list.type] }}</Badge>
                        <Badge variant="outline">{{ LIST_VISIBILITY_LABELS[list.visibility] }}</Badge>
                    </div>

                    <div class="text-sm text-muted-foreground">
                        {{ list.items_count ?? 0 }} {{ (list.items_count ?? 0) === 1 ? 'item' : 'items' }}
                    </div>
                </Link>
            </div>

            <!-- Delete dialog -->
            <Dialog :open="showDeleteDialog" @update:open="showDeleteDialog = $event">
                <DialogContent class="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Delete list?</DialogTitle>
                        <DialogDescription>
                            This will permanently delete "{{ deletingList?.name }}" and all its items. This cannot be undone.
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

            <!-- Modal -->
            <ListModal
                :list="editingList"
                :family-members="familyMembers"
                :open="showModal"
                @update:open="showModal = $event"
                @saved="onSaved"
            />
        </div>
    </AppLayout>
</template>
