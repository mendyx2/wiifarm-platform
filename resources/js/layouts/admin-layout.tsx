import { AppContent } from '@/components/app-content';
import AppLogo from '@/components/app-logo';
import { AppShell } from '@/components/app-shell';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { FlashMessage } from '@/components/flash-message';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    Banknote,
    Boxes,
    Combine,
    CreditCard,
    FileCheck2,
    KeyRound,
    Landmark,
    LayoutGrid,
    LogOut,
    Megaphone,
    Package,
    ScrollText,
    Settings,
    ShieldCheck,
    ShoppingBag,
    Store,
    Ticket,
    Users,
    type LucideIcon,
} from 'lucide-react';

type AdminNavItem = { title: string; url: string; icon: LucideIcon; permission?: string };

// Each item shows only to admins whose role allows it.
const navItems: AdminNavItem[] = [
    { title: 'Dashboard', url: '/admin', icon: LayoutGrid },
    { title: 'Members', url: '/admin/members', icon: Users, permission: 'members.view' },
    { title: 'KYC review', url: '/admin/kyc', icon: FileCheck2, permission: 'kyc.view' },
    { title: 'Online payments', url: '/admin/payments', icon: CreditCard, permission: 'payments.view' },
    { title: 'E-pins', url: '/admin/epins', icon: Ticket, permission: 'epins.view' },
    { title: 'Withdrawals', url: '/admin/withdrawals', icon: Banknote, permission: 'withdrawals.view' },
    { title: 'Orders', url: '/admin/orders', icon: ShoppingBag, permission: 'orders.view' },
    { title: 'Affiliates', url: '/admin/affiliates', icon: Megaphone, permission: 'affiliates.view' },
    { title: 'Stockists', url: '/admin/stockists', icon: Store, permission: 'stockists.view' },
    { title: 'Products', url: '/admin/products', icon: Package, permission: 'products.manage' },
    { title: 'Pools', url: '/admin/pools', icon: Landmark, permission: 'pools.view' },
    { title: 'Matching', url: '/admin/matching', icon: Combine, permission: 'matching.view' },
    { title: 'Packages', url: '/admin/packages', icon: Boxes, permission: 'settings.view' },
    { title: 'Settings', url: '/admin/settings', icon: Settings, permission: 'settings.view' },
    { title: 'Admins', url: '/admin/admins', icon: ShieldCheck, permission: 'admins.manage' },
    { title: 'Roles', url: '/admin/roles', icon: KeyRound, permission: 'roles.manage' },
    { title: 'Audit log', url: '/admin/audit', icon: ScrollText, permission: 'audit.view' },
];

const isCurrent = (url: string, current: string) =>
    url === '/admin' ? current === '/admin' : current === url || current.startsWith(url + '/') || current.startsWith(url + '?');

/** May the signed-in admin use this permission? Super-admins may use all. */
export function useAdminCan() {
    const admin = usePage<SharedData>().props.signedInAdmin;
    return (permission: string) => !!admin && (admin.isSuper || admin.permissions.includes(permission));
}

function AdminSidebar() {
    const page = usePage<SharedData>();
    const admin = page.props.signedInAdmin;
    const can = useAdminCan();

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/admin" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <SidebarGroup className="px-2 py-0">
                    <SidebarGroupLabel>Admin panel</SidebarGroupLabel>
                    <SidebarMenu>
                        {navItems
                            .filter((item) => !item.permission || can(item.permission))
                            .map((item) => (
                                <SidebarMenuItem key={item.title}>
                                    <SidebarMenuButton asChild isActive={isCurrent(item.url, page.url)}>
                                        <Link href={item.url} prefetch>
                                            <item.icon />
                                            <span>{item.title}</span>
                                        </Link>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                            ))}
                    </SidebarMenu>
                </SidebarGroup>
            </SidebarContent>

            <SidebarFooter>
                {admin && (
                    <div className="px-2 text-sm group-data-[collapsible=icon]:hidden">
                        <p className="truncate font-medium">{admin.name}</p>
                        <p className="truncate text-xs opacity-70">@{admin.username}</p>
                    </div>
                )}
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton asChild>
                            <Link href={route('admin.logout')} method="post" as="button" className="w-full">
                                <LogOut />
                                <span>Sign out</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarFooter>
        </Sidebar>
    );
}

export default function AdminLayout({ children, breadcrumbs = [] }: { children: React.ReactNode; breadcrumbs?: BreadcrumbItem[] }) {
    return (
        <AppShell variant="sidebar">
            <AdminSidebar />
            <AppContent variant="sidebar">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                <FlashMessage />
                {children}
            </AppContent>
        </AppShell>
    );
}
