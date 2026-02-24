<script setup lang="ts">
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import CookbookModal from '@/components/recipes/CookbookModal.vue';
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
import { Pencil, Plus, Trash2 } from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';
import type { FamilyMember } from '@/types/calendar';
import type { Cookbook } from '@/types/recipes';
import { COOKBOOK_VISIBILITY_LABELS } from '@/types/recipes';

const props = defineProps<{
    cookbooks: Cookbook[];
    familyMembers: FamilyMember[];
    can: {
        create: boolean;
        edit: boolean;
        delete: boolean;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Recipes', href: '/recipes' },
    { title: 'Cookbooks' },
];

const showModal = ref(false);
const editingCookbook = ref<Cookbook | null>(null);

function openCreate() {
    editingCookbook.value = null;
    showModal.value = true;
}

function openEdit(cookbook: Cookbook) {
    editingCookbook.value = cookbook;
    showModal.value = true;
}

function onSaved() {
    router.reload();
}

const showDeleteDialog = ref(false);
const deletingCookbook = ref<Cookbook | null>(null);
const isDeleting = ref(false);

function openDelete(cookbook: Cookbook) {
    deletingCookbook.value = cookbook;
    showDeleteDialog.value = true;
}

async function confirmDelete() {
    if (!deletingCookbook.value) return;
    isDeleting.value = true;

    await fetch(`/cookbooks/${deletingCookbook.value.id}`, {
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
                <h2 class="text-lg font-semibold">Cookbooks</h2>
                <Button v-if="can.create" size="sm" @click="openCreate">
                    <Plus class="h-4 w-4 mr-1" />
                    New Cookbook
                </Button>
            </div>

            <!-- Empty state -->
            <div v-if="cookbooks.length === 0" class="rounded-lg border border-dashed p-8 text-center text-muted-foreground">
                No cookbooks yet. {{ can.create ? 'Click "New Cookbook" to get started.' : '' }}
            </div>

            <!-- Card grid -->
            <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="cookbook in cookbooks"
                    :key="cookbook.id"
                    :href="`/cookbooks/${cookbook.id}`"
                    class="rounded-lg border p-4 space-y-3 hover:border-primary/50 transition-colors"
                >
                    <div class="flex items-start justify-between gap-2">
                        <span class="font-medium truncate">{{ cookbook.name }}</span>
                        <div v-if="can.edit || can.delete" class="flex items-center gap-1 shrink-0">
                            <Button v-if="can.edit" variant="outline" size="icon" class="h-8 w-8" @click.prevent="openEdit(cookbook)">
                                <Pencil class="h-3.5 w-3.5" />
                            </Button>
                            <Button v-if="can.delete" variant="outline" size="icon" class="h-8 w-8 text-destructive" @click.prevent="openDelete(cookbook)">
                                <Trash2 class="h-3.5 w-3.5" />
                            </Button>
                        </div>
                    </div>

                    <p v-if="cookbook.description" class="text-sm text-muted-foreground line-clamp-2">
                        {{ cookbook.description }}
                    </p>

                    <div class="flex items-center gap-2">
                        <Badge variant="outline">{{ COOKBOOK_VISIBILITY_LABELS[cookbook.visibility] }}</Badge>
                        <span class="text-sm text-muted-foreground">
                            {{ cookbook.recipes_count ?? 0 }} {{ (cookbook.recipes_count ?? 0) === 1 ? 'recipe' : 'recipes' }}
                        </span>
                    </div>
                </Link>
            </div>

            <!-- Delete dialog -->
            <Dialog :open="showDeleteDialog" @update:open="showDeleteDialog = $event">
                <DialogContent class="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Delete cookbook?</DialogTitle>
                        <DialogDescription>
                            This will permanently delete "{{ deletingCookbook?.name }}". Recipes in this cookbook will not be deleted. This cannot be undone.
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
            <CookbookModal
                :cookbook="editingCookbook"
                :family-members="familyMembers"
                :open="showModal"
                @update:open="showModal = $event"
                @saved="onSaved"
            />
        </div>
    </AppLayout>
</template>
