<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    Sheet,
    SheetContent,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import type { Contact } from '@/types/contacts';

const props = defineProps<{
    contact: Contact | null;
    open: boolean;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    saved: [];
}>();

const isEditing = computed(() => !!props.contact);
const isSaving = ref(false);
const errors = ref<Record<string, string[]>>({});

const firstName = ref('');
const lastName = ref('');
const phone = ref('');
const email = ref('');
const addressLine1 = ref('');
const addressLine2 = ref('');
const city = ref('');
const state = ref('');
const zip = ref('');
const dateOfBirth = ref('');
const notes = ref('');

watch(() => props.open, (open) => {
    if (!open) return;
    errors.value = {};

    if (props.contact) {
        firstName.value = props.contact.first_name;
        lastName.value = props.contact.last_name ?? '';
        phone.value = props.contact.phone ?? '';
        email.value = props.contact.email ?? '';
        addressLine1.value = props.contact.address_line_1 ?? '';
        addressLine2.value = props.contact.address_line_2 ?? '';
        city.value = props.contact.city ?? '';
        state.value = props.contact.state ?? '';
        zip.value = props.contact.zip ?? '';
        dateOfBirth.value = props.contact.date_of_birth?.slice(0, 10) ?? '';
        notes.value = props.contact.notes ?? '';
    } else {
        firstName.value = '';
        lastName.value = '';
        phone.value = '';
        email.value = '';
        addressLine1.value = '';
        addressLine2.value = '';
        city.value = '';
        state.value = '';
        zip.value = '';
        dateOfBirth.value = '';
        notes.value = '';
    }
});

async function save() {
    isSaving.value = true;
    errors.value = {};

    const body = {
        first_name: firstName.value,
        last_name: lastName.value || null,
        phone: phone.value || null,
        email: email.value || null,
        address_line_1: addressLine1.value || null,
        address_line_2: addressLine2.value || null,
        city: city.value || null,
        state: state.value || null,
        zip: zip.value || null,
        date_of_birth: dateOfBirth.value || null,
        notes: notes.value || null,
    };

    try {
        const url = isEditing.value ? `/contacts/${props.contact!.id}` : '/contacts';
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
            errors.value = { first_name: [`Save failed (${response.status}). Please try again.`] };
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
                <SheetTitle>{{ isEditing ? 'Edit Contact' : 'New Contact' }}</SheetTitle>
            </SheetHeader>

            <form @submit.prevent="save" class="flex flex-1 flex-col">
                <div class="space-y-4 px-4 flex-1">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="contact-first-name">First Name</Label>
                            <Input id="contact-first-name" v-model="firstName" required />
                            <p v-if="errors.first_name" class="text-sm text-destructive">{{ errors.first_name[0] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="contact-last-name">Last Name</Label>
                            <Input id="contact-last-name" v-model="lastName" />
                            <p v-if="errors.last_name" class="text-sm text-destructive">{{ errors.last_name[0] }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="contact-phone">Phone</Label>
                            <Input id="contact-phone" v-model="phone" type="tel" />
                            <p v-if="errors.phone" class="text-sm text-destructive">{{ errors.phone[0] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="contact-email">Email</Label>
                            <Input id="contact-email" v-model="email" type="email" />
                            <p v-if="errors.email" class="text-sm text-destructive">{{ errors.email[0] }}</p>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <Label for="contact-address1">Address Line 1</Label>
                        <Input id="contact-address1" v-model="addressLine1" />
                        <p v-if="errors.address_line_1" class="text-sm text-destructive">{{ errors.address_line_1[0] }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label for="contact-address2">Address Line 2</Label>
                        <Input id="contact-address2" v-model="addressLine2" />
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div class="space-y-2">
                            <Label for="contact-city">City</Label>
                            <Input id="contact-city" v-model="city" />
                            <p v-if="errors.city" class="text-sm text-destructive">{{ errors.city[0] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="contact-state">State</Label>
                            <Input id="contact-state" v-model="state" />
                            <p v-if="errors.state" class="text-sm text-destructive">{{ errors.state[0] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="contact-zip">Zip</Label>
                            <Input id="contact-zip" v-model="zip" />
                            <p v-if="errors.zip" class="text-sm text-destructive">{{ errors.zip[0] }}</p>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <Label for="contact-dob">Date of Birth</Label>
                        <Input id="contact-dob" v-model="dateOfBirth" type="date" />
                        <p v-if="errors.date_of_birth" class="text-sm text-destructive">{{ errors.date_of_birth[0] }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label for="contact-notes">Notes</Label>
                        <Textarea id="contact-notes" v-model="notes" placeholder="Optional notes" />
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
