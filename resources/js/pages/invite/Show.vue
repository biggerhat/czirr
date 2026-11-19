<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import AuthBase from '@/layouts/AuthLayout.vue';

const props = defineProps<{
    state: 'invalid' | 'guest' | 'ready';
    family?: string;
    joinedAs?: string | null;
    role?: 'parent' | 'child';
    token?: string;
}>();

const page = usePage();
const error = computed(
    () => (page.props.flash as { error?: string } | undefined)?.error,
);
const accepting = ref(false);

function accept() {
    accepting.value = true;
    router.post(
        `/invite/${props.token}/accept`,
        {},
        { onFinish: () => (accepting.value = false) },
    );
}
</script>

<template>
    <AuthBase
        :title="state === 'invalid' ? 'Invite not valid' : 'You\'re invited!'"
        :description="
            state === 'invalid'
                ? 'This link has expired or was already used.'
                : `${family} invited you to join their family${
                      joinedAs ? ` as ${joinedAs}` : ''
                  }.`
        "
    >
        <Head title="Family invite" />

        <p v-if="error" class="mb-4 text-center text-sm text-destructive">
            {{ error }}
        </p>

        <div v-if="state === 'invalid'" class="text-center text-sm">
            Ask whoever invited you to send a new link.
            <div class="mt-4">
                <Link href="/login" class="underline">Go to login</Link>
            </div>
        </div>

        <div v-else-if="state === 'guest'" class="flex flex-col gap-3">
            <Button as-child size="lg">
                <Link href="/register">Create an account</Link>
            </Button>
            <Button as-child variant="outline" size="lg">
                <Link href="/login">I already have an account</Link>
            </Button>
            <p class="text-center text-xs text-muted-foreground">
                You'll come back here to finish joining.
            </p>
        </div>

        <div v-else class="flex flex-col gap-3">
            <Button size="lg" :disabled="accepting" @click="accept">
                Join {{ family }}
            </Button>
            <p class="text-center text-xs text-muted-foreground">
                You'll join as a {{ role }}.
            </p>
        </div>
    </AuthBase>
</template>
