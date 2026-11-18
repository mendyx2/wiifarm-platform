import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DataTable } from '@/components/wf/data-table';
import { OrderStatusBadge, orderKindName } from '@/components/wf/order-badges';
import AdminLayout, { useAdminCan } from '@/layouts/admin-layout';
import { formatKobo } from '@/lib/money';
import { pv } from '@/lib/network';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { type ReactNode } from 'react';

interface OrderDetail {
    id: number;
    number: string;
    kind: string;
    status: string;
    amount: number;
    pv: number;
    units: number;
    paid_with: string | null;
    paid_at: string | null;
    fulfilled_at: string | null;
    created_at: string;
    code_hint: string | null;
    member: { id: number; username: string; name: string } | null;
    buyer: { name: string | null; phone: string | null; email: string | null } | null;
    items: { name: string; sku: string; quantity: number; unit_price: number; unit_pv: number }[];
}

const paidWith: Record<string, string> = { main: 'Main balance', product: 'Product wallet', package: 'Paid with the package', online: 'Online' };

function Row({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="flex flex-wrap justify-between gap-2 py-2">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="text-right font-medium">{children}</dd>
        </div>
    );
}

export default function AdminOrder({ order }: { order: OrderDetail }) {
    const can = useAdminCan();
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/admin' },
        { title: 'Orders', href: '/admin/orders' },
        { title: order.number, href: `/admin/orders/${order.id}` },
    ];

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={`Order ${order.number}`} />
            <div className="grid gap-6 p-4 lg:grid-cols-[2fr_1fr]">
                <div className="space-y-4">
                    <div className="flex flex-wrap items-center gap-2">
                        <h1 className="text-xl font-semibold">Order {order.number}</h1>
                        <OrderStatusBadge status={order.status} />
                    </div>
                    <DataTable
                        rows={order.items}
                        rowKey={(i) => i.sku}
                        columns={[
                            { key: 'name', header: 'Product', cell: (i) => `${i.name} (${i.sku})` },
                            { key: 'qty', header: 'Units', align: 'right', cell: (i) => i.quantity },
                            { key: 'price', header: 'Price a unit', align: 'right', cell: (i) => formatKobo(i.unit_price) },
                            { key: 'pv', header: 'PV a unit', align: 'right', cell: (i) => pv(i.unit_pv) },
                        ]}
                    />
                </div>
                <Card>
                    <CardHeader className="pb-2">
                        <CardTitle className="text-base">Details</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <dl className="divide-y text-sm">
                            <Row label="Kind">{orderKindName(order.kind)}</Row>
                            <Row label="Buyer">
                                {order.member ? (
                                    can('members.view') ? (
                                        <Link href={route('admin.members.show', order.member.id)} className="underline underline-offset-4">
                                            @{order.member.username}
                                        </Link>
                                    ) : (
                                        `@${order.member.username}`
                                    )
                                ) : (
                                    [order.buyer?.name, order.buyer?.phone].filter(Boolean).join(' · ') || '—'
                                )}
                            </Row>
                            <Row label="Amount">{formatKobo(order.amount)}</Row>
                            <Row label="PV">{pv(order.pv)}</Row>
                            <Row label="Units">{order.units}</Row>
                            <Row label="Paid with">{order.paid_with ? (paidWith[order.paid_with] ?? order.paid_with) : '—'}</Row>
                            <Row label="Paid">{order.paid_at ?? 'Not yet'}</Row>
                            <Row label="Collected">{order.fulfilled_at ?? 'Not yet'}</Row>
                            <Row label="Pickup code">{order.code_hint ? `ends in …${order.code_hint}` : '—'}</Row>
                        </dl>
                        <p className="text-muted-foreground mt-3 text-xs">Only the buyer is shown the full code.</p>
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
