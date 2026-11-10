import { AppContent } from '@/components/app-content';
import AppLogo from '@/components/app-logo';
import { AppShell } from '@/components/app-shell';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
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
import { type AdminData, type BreadcrumbItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { LayoutGrid, LogOut, type LucideIcon } from 'lucide-react';

type AdminNavItem = { title: string; url: string; icon: LucideIcon; permission?: string };

// Later parts add Members, KYC, Settings, Admins and the audit log, each shown
// only to admins whose role allows it.
const navItems: AdminNavItem[] = [{ title: 'Dashboard', url: '/admin', icon: LayoutGrid }];

/** May the signed-in admin use this permission? Super-admins may use all. */
export function useAdminCan() {
    const admin = usePage<SharedData>().props.admin as AdminData | null;
    return (permission: string) => !!admin && (admin.isSuper || admin.permissions.includes(permission));
}

function AdminSidebar() {
    const page = usePage<SharedData>();
    const admin = page.props.admin as AdminData | null;
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
                                    <SidebarMenuButton asChild isActive={item.url === page.url}>
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
                {children}
            </AppContent>
        </AppShell>
    );
}
