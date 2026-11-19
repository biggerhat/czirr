<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { onMounted } from 'vue';
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import { api } from '@/lib/api';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

// Chores, digests and "today" use the account timezone. New accounts default to
// UTC, so adopt the browser's timezone once (the server never overrides a
// timezone that was chosen on purpose).
const page = usePage();

onMounted(async () => {
    try {
        const current = page.props.auth?.user?.timezone as string | undefined;
        const browser = Intl.DateTimeFormat().resolvedOptions().timeZone;

        if (
            (current && current !== 'UTC') ||
            !browser ||
            browser === 'UTC' ||
            sessionStorage.getItem('tz-detected')
        ) {
            return;
        }

        sessionStorage.setItem('tz-detected', '1');
        const res = await api('POST', '/settings/timezone/detect', {
            timezone: browser,
        });
        if (res.ok) {
            router.reload({ only: ['auth'] });
        }
    } catch {
        // Best effort only.
    }
});
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="overflow-x-hidden">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <slot />
        </AppContent>
    </AppShell>
</template>
