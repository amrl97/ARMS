<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import {
    Building2,
    ChartNoAxesCombined,
    House,
    LayoutGrid,
    Settings2,
    ShieldCheck,
    UsersRound,
    SquareText,
} from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
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
import users from '@/routes/users';
import type { NavItem } from '@/types';

const page = usePage();
const mainNavItems = computed<NavItem[]>(() => {
    const roles = page.props.auth.roles;
    const isAdmin = roles.includes('Super Admin') || roles.includes('Admin');
    const isApprover = roles.includes('Approver') || page.props.auth.canApprove;
    const items: NavItem[] = [
        { title: 'Home', href: '/home', icon: House },
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: ChartNoAxesCombined,
        },
    ];

    if (isAdmin) {
        items.push(
            {
                title: 'Users',
                href: users.index(),
                icon: UsersRound,
            },
            {
                title: 'Roles',
                href: '/roles',
                icon: ShieldCheck,
            },
            {
                title: 'Departments',
                href: '/departments',
                icon: Building2,
            },
            {
                title: 'Configs',
                href: '/admin/configs',
                icon: Settings2,
            },
        );
    } else {
        items.push({
            title: 'Requests',
            href: '/requests',
            icon: SquareText,
        });
    }

    if (isAdmin || isApprover) {
        items.push({
            title: 'Approvals',
            href: '/approvals',
            icon: ShieldCheck,
        });
    }

    return items;
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset" class="border-0">
        <SidebarHeader class="px-3 pt-5 pb-3">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        size="lg"
                        as-child
                        class="hover:bg-sidebar-accent rounded-xl px-2"
                    >
                        <Link href="/home">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter class="px-3 pb-4">
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
