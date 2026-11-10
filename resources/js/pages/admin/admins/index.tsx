import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/wf/data-table';
import AdminLayout from '@/layouts/admin-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';

type Row = { id: number; name: string; username: string; email: string; is_active: boolean; roles: string[]; last_sign_in_at: string | null };

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin' },
    { title: 'Admins', href: '/admin/admins' },
];

export default function AdminsIndex({ admins }: { admins: Row[] }) {
    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Admins" />
            <div className="space-y-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <p className="text-muted-foreground text-sm">
                        The people who can sign in to this panel. Admins are deactivated, never deleted, so the audit log always knows who did what.
                    </p>
                    <Button asChild>
                        <Link href={route('admin.admins.create')}>
                            <Plus className="size-4" />
                            Add an admin
                        </Link>
                    </Button>
                </div>
                <DataTable
                    rows={admins}
                    rowKey={(a) => a.id}
                    columns={[
                        {
                            key: 'admin',
                            header: 'Admin',
                            cell: (a) => (
                                <Link href={route('admin.admins.edit', a.id)} className="group block">
                                    <span className="font-medium group-hover:underline">{a.name}</span>
                                    <span className="text-muted-foreground block text-xs">
                                        @{a.username} · {a.email}
                                    </span>
                                </Link>
                            ),
                        },
                        {
                            key: 'roles',
                            header: 'Roles',
                            cell: (a) => (
                                <div className="flex flex-wrap gap-1">
                                    {a.roles.map((r) => (
                                        <Badge key={r} variant="secondary">
                                            {r}
                                        </Badge>
                                    ))}
                                </div>
                            ),
                        },
                        {
                            key: 'state',
                            header: 'State',
                            cell: (a) => (a.is_active ? <Badge variant="success">Active</Badge> : <Badge variant="danger">Deactivated</Badge>),
                        },
                        { key: 'last', header: 'Last sign-in', cell: (a) => a.last_sign_in_at ?? 'Never', className: 'whitespace-nowrap' },
                    ]}
                />
            </div>
        </AdminLayout>
    );
}
