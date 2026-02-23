<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { BookOpen, BookUser, CalendarDays, ClipboardList, CookingPot, Folder, LayoutGrid, ListChecks, UtensilsCrossed, Users, Wallet } from 'lucide-vue-next';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import AppLogo from './AppLogo.vue';
import { index as calendarIndex } from '@/routes/calendar';
import { usePermissions } from '@/composables/usePermissions';

const { can } = usePermissions();

type NavItemWithPermission = NavItem & { permission?: string };

const allNavItems: NavItemWithPermission[] = [
    {
        title: 'Dashboard',
        href: '/',
        icon: LayoutGrid,
    },
    {
        title: 'Calendar',
        href: calendarIndex(),
        icon: CalendarDays,
    },
    {
        title: 'Lists',
        href: '/lists',
        icon: ListChecks,
    },
    {
        title: 'Recipes',
        href: '/recipes',
        icon: CookingPot,
    },
    {
        title: 'Meal Plans',
        href: '/meal-plans',
        icon: UtensilsCrossed,
    },
    {
        title: 'Chores',
        href: '/chores',
        icon: ClipboardList,
    },
    {
        title: 'Contacts',
        href: '/contacts',
        icon: BookUser,
    },
    {
        title: 'Budget',
        href: '/budgeting',
        icon: Wallet,
        permission: 'budgeting.view',
    },
    {
        title: 'Family',
        href: '/family',
        icon: Users,
        permission: 'family.view',
    },
];

const mainNavItems = computed(() =>
    allNavItems.filter(item => !item.permission || can(item.permission)),
);

const footerNavItems: NavItem[] = [
    // {
    //     title: 'Github Repo',
    //     href: 'https://github.com/laravel/vue-starter-kit',
    //     icon: Folder,
    // },
    // {
    //     title: 'Documentation',
    //     href: 'https://laravel.com/docs/starter-kits#vue',
    //     icon: BookOpen,
    // },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link href="/">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
