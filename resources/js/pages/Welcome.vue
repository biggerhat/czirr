<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { dashboard, login, register } from '@/routes';

withDefaults(
    defineProps<{
        canRegister: boolean;
    }>(),
    {
        canRegister: true,
    },
);
</script>

<template>
    <Head title="Welcome" />
    <div class="flex min-h-screen flex-col items-center justify-center bg-background p-6">
        <nav class="flex items-center gap-4">
            <Link
                v-if="$page.props.auth.user"
                :href="dashboard()"
                class="inline-block rounded-md border border-border px-5 py-1.5 text-sm text-foreground hover:bg-accent"
            >
                Dashboard
            </Link>
            <template v-else>
                <Link
                    :href="login()"
                    class="inline-block rounded-md border border-border px-5 py-1.5 text-sm text-foreground hover:bg-accent"
                >
                    Log in
                </Link>
                <Link
                    v-if="canRegister"
                    :href="register()"
                    class="inline-block rounded-md bg-primary px-5 py-1.5 text-sm text-primary-foreground hover:bg-primary/90"
                >
                    Register
                </Link>
            </template>
        </nav>
    </div>
</template>
