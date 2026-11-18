import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/wf/data-table';
import AdminLayout, { useAdminCan } from '@/layouts/admin-layout';
import { formatKobo } from '@/lib/money';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';

type Row = { id: number; name: string; kind: string; status: string; state: string; owner: string; mega: string | null; wallet: number };

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin' },
    { title: 'Stockists', href: '/admin/stockists' },
];

export function StoreStatusBadge({ status }: { status: string }) {
    if (status === 'active') return <Badge variant="success">Active</Badge>;
    if (status === 'pending') return <Badge variant="warning">Waiting for deposit</Badge>;
    return <Badge variant="danger">Deactivated</Badge>;
}

export default function Stockists({ stores }: { stores: Row[] }) {
    const can = useAdminCan();

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Stockists" />
            <div className="space-y-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <p className="text-muted-foreground text-sm">
                        Mega stores receive stock from the manufacturer; Sub stores collect from their Mega store.
                    </p>
                    {can('stockists.manage') && (
                        <Button asChild>
                            <Link href={route('admin.stockists.create')}>
                                <Plus className="size-4" />
                                Set up a store
                            </Link>
                        </Button>
                    )}
                </div>
                <DataTable
                    rows={stores}
                    rowKey={(s) => s.id}
                    emptyTitle="No stores yet"
                    columns={[
                        {
                            key: 'name',
                            header: 'Store',
                            cell: (s) => (
                                <Link href={route('admin.stockists.show', s.id)} className="group block">
                                    <span className="font-medium group-hover:underline">{s.name}</span>
                                    <span className="text-muted-foreground block text-xs">
                                        @{s.owner} · {s.state}
                                    </span>
                                </Link>
                            ),
                        },
                        { key: 'kind', header: 'Kind', cell: (s) => (s.kind === 'mega' ? 'Mega' : `Sub, under ${s.mega ?? '—'}`) },
                        { key: 'status', header: 'Status', cell: (s) => <StoreStatusBadge status={s.status} /> },
                        { key: 'wallet', header: 'Stockist wallet', align: 'right', cell: (s) => formatKobo(s.wallet) },
                    ]}
                />
            </div>
        </AdminLayout>
    );
}
