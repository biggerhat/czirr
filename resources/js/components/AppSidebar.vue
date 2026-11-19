<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    BookUser,
    CalendarDays,
    ClipboardList,
    CookingPot,
    Dices,
    Gamepad2,
    Gift,
    LayoutGrid,
    MessagesSquare,
    PiggyBank,
    Sun,
    ListChecks,
    Refrigerator,
    Trophy,
    UtensilsCrossed,
    Users,
    Wallet,
} from 'lucide-vue-next';
import { computed } from 'vue';
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
import { usePermissions } from '@/composables/usePermissions';
import { index as calendarIndex } from '@/routes/calendar';
import { type NavItem } from '@/types';
import AppLogo from './AppLogo.vue';

const { can } = usePermissions();

type NavItemWithPermission = NavItem & { permission?: string; group: string };

const groupOrder = ['Home', 'Food', 'Chores & fun', 'Family'];

const allNavItems: NavItemWithPermission[] = [
    {
        title: 'Dashboard',
        href: '/',
        icon: LayoutGrid,
        group: 'Home',
    },
    {
        title: 'Calendar',
        href: calendarIndex(),
        icon: CalendarDays,
        group: 'Home',
    },
    {
        title: 'Board',
        href: '/board',
        icon: MessagesSquare,
        permission: 'lists.view',
        group: 'Home',
    },
    {
        title: 'My Day',
        href: '/my-day',
        icon: Sun,
        permission: 'chores.view',
        group: 'Home',
    },
    {
        title: 'Lists',
        href: '/lists',
        icon: ListChecks,
        group: 'Food',
    },
    {
        title: 'Recipes',
        href: '/recipes',
        icon: CookingPot,
        group: 'Food',
    },
    {
        title: 'Meal Plans',
        href: '/meal-plans',
        icon: UtensilsCrossed,
        group: 'Food',
    },
    {
        title: 'Pantry',
        href: '/pantry',
        icon: Refrigerator,
        permission: 'meal-plans.view',
        group: 'Food',
    },
    {
        title: 'Chores',
        href: '/chores',
        icon: ClipboardList,
        group: 'Chores & fun',
    },
    {
        title: 'Scoreboard',
        href: '/scoreboard',
        icon: Trophy,
        permission: 'chores.view',
        group: 'Chores & fun',
    },
    {
        title: 'Savings',
        href: '/savings',
        icon: PiggyBank,
        permission: 'chores.view',
        group: 'Chores & fun',
    },
    {
        title: 'Rewards',
        href: '/rewards',
        icon: Gift,
        permission: 'chores.view',
        group: 'Chores & fun',
    },
    {
        title: 'Yahtzee',
        href: '/yahtzee',
        icon: Dices,
        group: 'Chores & fun',
    },
    {
        title: 'Game night',
        href: '/games',
        icon: Gamepad2,
        permission: 'lists.view',
        group: 'Chores & fun',
    },
    {
        title: 'Contacts',
        href: '/contacts',
        icon: BookUser,
        group: 'Family',
    },
    {
        title: 'Budget',
        href: '/budgeting',
        icon: Wallet,
        permission: 'budgeting.view',
        group: 'Family',
    },
    {
        title: 'Family',
        href: '/family',
        icon: Users,
        permission: 'family.view',
        group: 'Family',
    },
];

const navGroups = computed(() =>
    groupOrder
        .map((label) => ({
            label,
            items: allNavItems.filter(
                (item) =>
                    item.group === label &&
                    (!item.permission || can(item.permission)),
            ),
        }))
        .filter((g) => g.items.length > 0),
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
    <Sidebar collapsible="icon" variant="inset" role="complementary" aria-label="Sidebar">
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
            <NavMain
                v-for="g in navGroups"
                :key="g.label"
                :label="g.label"
                :items="g.items"
            />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
