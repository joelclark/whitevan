import { Link, usePage } from '@inertiajs/react';
import { Activity, LayoutGrid, Settings, Shield } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
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
import { index as adminUsersIndex } from '@/routes/admin/users';
import { index as sysopsAccountsIndex } from '@/routes/sysops/accounts';
import { index as sysopsActivityLogsIndex } from '@/routes/sysops/activity-logs';
import type { Auth, NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
];

const adminNavItem: NavItem = {
    title: 'Account Settings',
    href: adminUsersIndex().url,
    icon: Settings,
};

const sysopsNavItems: NavItem[] = [
    {
        title: 'Accounts',
        href: sysopsAccountsIndex().url,
        icon: Shield,
    },
    {
        title: 'Activity Log',
        href: sysopsActivityLogsIndex().url,
        icon: Activity,
        cacheFor: 0,
    },
];

export function AppSidebar() {
    const { auth } = usePage<{ auth: Auth }>().props;

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
                {auth.is_sysop && (
                    <NavMain items={sysopsNavItems} label="Sysops" />
                )}
            </SidebarContent>

            <SidebarFooter>
                {auth.security_groups.includes('admin') && (
                    <NavMain items={[adminNavItem]} label="" />
                )}
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
