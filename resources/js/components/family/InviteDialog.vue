<script setup lang="ts">
import { Check, Copy, Share2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { api } from '@/lib/api';

const props = defineProps<{
    open: boolean;
    /** Invite an existing, not-yet-linked family member. Omit to invite someone new. */
    member?: { id: number; name: string } | null;
}>();
const emit = defineEmits<{ 'update:open': [value: boolean] }>();

const name = ref('');
const role = ref<'parent' | 'child'>('child');
const url = ref<string | null>(null);
const error = ref<string | null>(null);
const busy = ref(false);
const copied = ref(false);
const canShare = typeof navigator !== 'undefined' && 'share' in navigator;

watch(
    () => props.open,
    (open) => {
        if (!open) return;
        name.value = '';
        role.value = 'child';
        url.value = null;
        error.value = null;
        copied.value = false;
        // Existing members already have a name and role, so there's nothing to ask.
        if (props.member) create();
    },
);

async function create() {
    busy.value = true;
    error.value = null;
    const res = await api<{ url: string }>('POST', '/family/invites', {
        family_member_id: props.member?.id ?? null,
        name: props.member ? null : name.value || null,
        role: role.value,
    });
    busy.value = false;
    if (res.ok && res.data) url.value = res.data.url;
    else error.value = res.message ?? 'Could not create the invite.';
}

async function copy() {
    if (!url.value) return;
    try {
        await navigator.clipboard.writeText(url.value);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    } catch {
        // Clipboard can be blocked; the link is selectable in the field anyway.
    }
}

function share() {
    if (url.value)
        navigator
            .share({ title: 'Join our family', url: url.value })
            .catch(() => {});
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>
                    {{ member ? `Invite ${member.name}` : 'Invite someone' }}
                </DialogTitle>
                <DialogDescription>
                    Send them this link. They'll create an account (or log in)
                    and join your family. It works once and expires in 7 days.
                </DialogDescription>
            </DialogHeader>

            <div v-if="!member && !url" class="space-y-3">
                <Input v-model="name" placeholder="Their name (optional)" />
                <div class="flex gap-2">
                    <Button
                        type="button"
                        size="sm"
                        :variant="role === 'child' ? 'default' : 'outline'"
                        @click="role = 'child'"
                    >
                        Child
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        :variant="role === 'parent' ? 'default' : 'outline'"
                        @click="role = 'parent'"
                    >
                        Parent
                    </Button>
                </div>
            </div>

            <p v-if="error" class="text-sm text-destructive">{{ error }}</p>
            <p v-if="busy" class="text-sm text-muted-foreground">
                Creating link…
            </p>

            <div v-if="url" class="space-y-2">
                <Input
                    :model-value="url"
                    readonly
                    @focus="($event.target as HTMLInputElement).select()"
                />
                <div class="flex gap-2">
                    <Button type="button" class="flex-1" @click="copy">
                        <component
                            :is="copied ? Check : Copy"
                            class="mr-2 h-4 w-4"
                        />
                        {{ copied ? 'Copied' : 'Copy link' }}
                    </Button>
                    <Button
                        v-if="canShare"
                        type="button"
                        variant="outline"
                        @click="share"
                    >
                        <Share2 class="mr-2 h-4 w-4" /> Share
                    </Button>
                </div>
            </div>

            <DialogFooter v-if="!member && !url">
                <Button type="button" :disabled="busy" @click="create">
                    Create link
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
