import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { DataTable } from '@/components/wf/data-table';
import { OrderStatusBadge, orderKindName } from '@/components/wf/order-badges';
import { Pagination, type Paginated } from '@/components/wf/pagination';
import AdminLayout from '@/layouts/admin-layout';
import { formatKobo } from '@/lib/money';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

type Row = { id: number; number: string; kind: string; status: string; buyer: string; amount: number; units: number; created_at: string };
type Filters = { q?: string; status?: string; kind?: string };

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin' },
    { title: 'Orders', href: '/admin/orders' },
];

export default function AdminOrders({ orders, filters }: { orders: Paginated<Row>; filters: Filters }) {
    const [q, setQ] = useState(filters.q ?? '');
    const apply = (next: Filters) =>
        router.get(route('admin.orders.index'), Object.fromEntries(Object.entries({ ...filters, ...next }).filter(([, v]) => v)), {
            preserveState: true,
            replace: true,
        });
    const search: FormEventHandler = (e) => {
        e.preventDefault();
        apply({ q });
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Orders" />
            <div className="space-y-4 p-4">
                <div className="flex flex-wrap items-end gap-3">
                    <form onSubmit={search} className="flex min-w-64 flex-1 gap-2">
                        <Input
                            aria-label="Search orders"
                            placeholder="Order number, member or buyer"
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                        />
                        <Button type="submit" variant="secondary">
                            <Search className="size-4" />
                            Search
                        </Button>
                    </form>
                    <select
                        aria-label="Status"
                        className="border-input bg-background h-10 rounded-md border px-3 text-sm"
                        value={filters.status ?? ''}
                        onChange={(e) => apply({ status: e.target.value })}
                    >
                        <option value="">Any status</option>
                        <option value="pending">Waiting for payment</option>
                        <option value="paid">Ready to collect</option>
                        <option value="fulfilled">Collected</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                    <select
                        aria-label="Kind"
                        className="border-input bg-background h-10 rounded-md border px-3 text-sm"
                        value={filters.kind ?? ''}
                        onChange={(e) => apply({ kind: e.target.value })}
                    >
                        <option value="">Every kind</option>
                        <option value="registration">Registration packs</option>
                        <option value="repurchase">Repurchase</option>
                        <option value="public">Public shop</option>
                    </select>
                </div>
                <DataTable
                    rows={orders.data}
                    rowKey={(o) => o.id}
                    emptyTitle="No orders found"
                    columns={[
                        {
                            key: 'number',
                            header: 'Order',
                            cell: (o) => (
                                <Link href={route('admin.orders.show', o.id)} className="font-medium underline-offset-4 hover:underline">
                                    {o.number}
                                </Link>
                            ),
                        },
                        { key: 'kind', header: 'Kind', cell: (o) => orderKindName(o.kind) },
                        { key: 'buyer', header: 'Buyer', cell: (o) => o.buyer },
                        { key: 'status', header: 'Status', cell: (o) => <OrderStatusBadge status={o.status} /> },
                        { key: 'units', header: 'Units', align: 'right', cell: (o) => o.units },
                        { key: 'amount', header: 'Amount', align: 'right', cell: (o) => formatKobo(o.amount) },
                        { key: 'at', header: 'Placed', cell: (o) => o.created_at, className: 'whitespace-nowrap' },
                    ]}
                />
                <Pagination page={orders} />
            </div>
        </AdminLayout>
    );
}
