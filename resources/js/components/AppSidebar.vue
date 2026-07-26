<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Activity,
    BookOpen,
    CalendarDays,
    CalendarRange,
    Dumbbell,
    FolderGit2,
    Gauge,
    HeartPulse,
    LayoutGrid,
    Medal,
    Target,
    Trophy,
} from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
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
import { dashboard } from '@/routes';
import { index as activitiesIndex } from '@/routes/activities';
import { index as goalsIndex } from '@/routes/goals';
import { index as leaderboardIndex } from '@/routes/leaderboard';
import { index as pacingIndex } from '@/routes/pacing';
import { index as planIndex } from '@/routes/plan';
import { index as recapIndex } from '@/routes/recap';
import { index as recordsIndex } from '@/routes/records';
import { index as wellnessIndex } from '@/routes/wellness';
import { index as workoutsIndex } from '@/routes/workouts';
import type { NavItem } from '@/types';

const navGroups: { label: string; items: NavItem[] }[] = [
    {
        label: 'Train',
        items: [
            { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
            { title: 'Activities', href: activitiesIndex(), icon: Activity },
            { title: 'Plan', href: planIndex(), icon: CalendarDays },
            { title: 'Workouts', href: workoutsIndex(), icon: Dumbbell },
        ],
    },
    {
        label: 'Analyse',
        items: [
            { title: 'Records', href: recordsIndex(), icon: Trophy },
            { title: 'Leaderboard', href: leaderboardIndex(), icon: Medal },
            { title: 'Wellness', href: wellnessIndex(), icon: HeartPulse },
            { title: 'Recap', href: recapIndex(), icon: CalendarRange },
        ],
    },
    {
        label: 'Goals & tools',
        items: [
            { title: 'Goals', href: goalsIndex(), icon: Target },
            { title: 'Pacing', href: pacingIndex(), icon: Gauge },
        ],
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#vue',
        icon: BookOpen,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :groups="navGroups" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
