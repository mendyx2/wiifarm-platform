import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { BadgeCheck, LayoutGrid, Network, ShoppingBag, ShoppingCart, Store, Wallet } from 'lucide-react';
import AppLogo from './app-logo';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        url: '/dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'My package',
        url: '/package',
        icon: BadgeCheck,
    },
    {
        title: 'Shop',
        url: '/shop',
        icon: ShoppingCart,
    },
    {
        title: 'My orders',
        url: '/orders',
        icon: ShoppingBag,
    },
    {
        title: 'My team',
        url: '/team',
        icon: Network,
    },
    {
        title: 'Wallet',
        url: '/wallet',
        icon: Wallet,
    },
];

// Links shown at the foot of the sidebar; none yet.
const footerNavItems: NavItem[] = [];

export function AppSidebar() {
    const { hasStore } = usePage<SharedData>().props;
    const items: NavItem[] = hasStore ? [...mainNavItems, { title: 'My store', url: '/store', icon: Store }] : mainNavItems;
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={items} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
