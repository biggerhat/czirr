<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import ContactModal from '@/components/contacts/ContactModal.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Mail, MapPin, Pencil, Phone, Plus, Search, Trash2 } from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';
import type { Contact } from '@/types/contacts';

const props = defineProps<{
    contacts: Contact[];
    search: string;
    can: {
        create: boolean;
        edit: boolean;
        delete: boolean;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Contacts' },
];

const searchQuery = ref(props.search);
let searchTimeout: ReturnType<typeof setTimeout> | null = null;

function onSearch() {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get('/contacts', searchQuery.value ? { search: searchQuery.value } : {}, {
            preserveState: true,
            replace: true,
        });
    }, 300);
}

const showModal = ref(false);
const editingContact = ref<Contact | null>(null);

function openCreate() {
    editingContact.value = null;
    showModal.value = true;
}

function openEdit(contact: Contact) {
    editingContact.value = contact;
    showModal.value = true;
}

function onSaved() {
    router.reload();
}

const showDeleteDialog = ref(false);
const deletingContact = ref<Contact | null>(null);
const isDeleting = ref(false);

function openDelete(contact: Contact) {
    deletingContact.value = contact;
    showDeleteDialog.value = true;
}

async function confirmDelete() {
    if (!deletingContact.value) return;
    isDeleting.value = true;

    await fetch(`/contacts/${deletingContact.value.id}`, {
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

function formatName(contact: Contact): string {
    return [contact.first_name, contact.last_name].filter(Boolean).join(' ');
}

function formatLocation(contact: Contact): string {
    return [contact.city, contact.state].filter(Boolean).join(', ');
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <!-- Header -->
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-lg font-semibold">Address Book</h2>
                <div class="flex items-center gap-2">
                    <div class="relative">
                        <Search class="absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            v-model="searchQuery"
                            placeholder="Search contacts..."
                            class="pl-9 w-[250px]"
                            @input="onSearch"
                        />
                    </div>
                    <Button v-if="can.create" size="sm" @click="openCreate">
                        <Plus class="h-4 w-4 mr-1" />
                        Add Contact
                    </Button>
                </div>
            </div>

            <!-- Empty state -->
            <div v-if="contacts.length === 0" class="rounded-lg border border-dashed p-8 text-center text-muted-foreground">
                <template v-if="search">
                    No contacts match "{{ search }}".
                </template>
                <template v-else>
                    No contacts yet. Click "Add Contact" to get started.
                </template>
            </div>

            <!-- Contact cards -->
            <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="contact in contacts"
                    :key="contact.id"
                    class="rounded-lg border p-4 space-y-2 hover:border-primary/50 transition-colors"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div class="font-medium">{{ formatName(contact) }}</div>
                        <div v-if="can.edit || can.delete" class="flex items-center gap-1 shrink-0">
                            <Button v-if="can.edit" variant="outline" size="icon" class="h-7 w-7" @click="openEdit(contact)">
                                <Pencil class="h-3.5 w-3.5" />
                            </Button>
                            <Button v-if="can.delete" variant="outline" size="icon" class="h-7 w-7 text-destructive" @click="openDelete(contact)">
                                <Trash2 class="h-3.5 w-3.5" />
                            </Button>
                        </div>
                    </div>
                    <div class="space-y-1 text-sm text-muted-foreground">
                        <div v-if="contact.phone" class="flex items-center gap-2">
                            <Phone class="h-3.5 w-3.5 shrink-0" />
                            {{ contact.phone }}
                        </div>
                        <div v-if="contact.email" class="flex items-center gap-2">
                            <Mail class="h-3.5 w-3.5 shrink-0" />
                            {{ contact.email }}
                        </div>
                        <div v-if="formatLocation(contact)" class="flex items-center gap-2">
                            <MapPin class="h-3.5 w-3.5 shrink-0" />
                            {{ formatLocation(contact) }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Delete dialog -->
            <Dialog :open="showDeleteDialog" @update:open="showDeleteDialog = $event">
                <DialogContent class="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Delete contact?</DialogTitle>
                        <DialogDescription>
                            This will permanently delete "{{ deletingContact ? formatName(deletingContact) : '' }}". This cannot be undone.
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
            <ContactModal
                :contact="editingContact"
                :open="showModal"
                @update:open="showModal = $event"
                @saved="onSaved"
            />
        </div>
    </AppLayout>
</template>
