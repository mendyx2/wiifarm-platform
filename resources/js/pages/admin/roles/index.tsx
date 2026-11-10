import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/wf/data-table';
import AdminLayout from '@/layouts/admin-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';

type Row = { id: number; name: string; description: string | null; is_super: boolean; permissions: number; admins: number };

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin' },
    { title: 'Roles', href: '/admin/roles' },
];

export default function RolesIndex({ roles, total }: { roles: Row[]; total: number }) {
    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Roles" />
            <div className="space-y-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <p className="text-muted-foreground text-sm">A role is a set of permissions. Give each admin only the roles their job needs.</p>
                    <Button asChild>
                        <Link href={route('admin.roles.create')}>
                            <Plus className="size-4" />
                            Create a role
                        </Link>
                    </Button>
                </div>
                <DataTable
                    rows={roles}
                    rowKey={(r) => r.id}
                    columns={[
                        {
                            key: 'role',
                            header: 'Role',
                            cell: (r) => (
                                <Link href={route('admin.roles.edit', r.id)} className="group block">
                                    <span className="font-medium group-hover:underline">{r.name}</span>{' '}
                                    {r.is_super && <Badge variant="gold">Everything</Badge>}
                                    {r.description && <span className="text-muted-foreground block text-xs">{r.description}</span>}
                                </Link>
                            ),
                        },
                        { key: 'permissions', header: 'Permissions', align: 'right', cell: (r) => `${r.permissions} of ${total}` },
                        { key: 'admins', header: 'Admins', align: 'right', cell: (r) => r.admins },
                    ]}
                />
            </div>
        </AdminLayout>
    );
}
