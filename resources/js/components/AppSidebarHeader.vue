<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';

const props = withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

// Every page gets an <h1> for screen readers, named after the current page.
const pageTitle = computed(
    () => props.breadcrumbs[props.breadcrumbs.length - 1]?.title ?? '',
);

// Pages that draw their own <h1> keep it; only add the hidden one when missing.
const page = usePage();
const needsH1 = ref(true);
const checkH1 = () =>
    nextTick(() => {
        // Ignore headings that exist only for print (display:none on screen).
        needsH1.value = ![
            ...document.querySelectorAll('main h1:not([data-auto-h1])'),
        ].some((h) => h.getClientRects().length > 0);
    });
onMounted(checkH1);
watch(() => page.url, checkH1);
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
    >
        <h1 v-if="pageTitle && needsH1" data-auto-h1 class="sr-only">
            {{ pageTitle }}
        </h1>
        <div class="flex items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>
    </header>
</template>
