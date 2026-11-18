import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { EmptyState } from '@/components/wf/empty-state';
import { OrderStatusBadge, orderKindName } from '@/components/wf/order-badges';
import { Pagination, type Paginated } from '@/components/wf/pagination';
import AppLayout from '@/layouts/app-layout';
import { formatKobo } from '@/lib/money';
import { pv } from '@/lib/network';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Copy, ShoppingBag } from 'lucide-react';
import { useState } from 'react';

type Order = {
    id: number;
    number: string;
    kind: string;
    status: string;
    amount: number;
    pv: number;
    units: number;
    pickup_code: string | null;
    paid_at: string | null;
    fulfilled_at: string | null;
    items: { name: string; quantity: number }[];
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'My orders', href: '/orders' }];

function PickupCode({ code }: { code: string }) {
    const [copied, setCopied] = useState(false);
    const copy = () => {
        navigator.clipboard.writeText(code).then(
            () => setCopied(true),
            () => setCopied(false),
        );
    };

    return (
        <div className="bg-accent text-accent-foreground grid gap-1 rounded-lg p-4">
            <span className="text-xs font-medium tracking-wide uppercase">Pickup code</span>
            <div className="flex flex-wrap items-center gap-3">
                <span className="font-mono text-2xl font-semibold tracking-widest select-all">{code}</span>
                <Button type="button" size="sm" variant="secondary" onClick={copy}>
                    <Copy className="size-4" />
                    {copied ? 'Copied' : 'Copy'}
                </Button>
            </div>
            <span className="text-xs">Show it at any stockist. It never expires; the whole order is handed over at once. Keep it private.</span>
        </div>
    );
}

export default function Orders({ orders }: { orders: Paginated<Order> }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="My orders" />
            <div className="space-y-4 p-4">
                {orders.data.length === 0 ? (
                    <EmptyState
                        icon={ShoppingBag}
                        title="No orders yet"
                        description="Your registration packs and every product you buy appear here, with the code to collect them."
                        action={
                            <Button asChild>
                                <Link href={route('shop')}>Go to the shop</Link>
                            </Button>
                        }
                    />
                ) : (
                    orders.data.map((o) => (
                        <Card key={o.id} className="py-0">
                            <CardContent className="grid gap-4 p-5 md:grid-cols-[1fr_auto]">
                                <div className="grid gap-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="font-medium">Order {o.number}</span>
                                        <OrderStatusBadge status={o.status} />
                                        <span className="text-muted-foreground text-sm">{orderKindName(o.kind)}</span>
                                    </div>
                                    <p className="text-sm">{o.items.map((i) => `${i.quantity} × ${i.name}`).join(', ')}</p>
                                    <p className="text-muted-foreground text-sm tabular-nums">
                                        {formatKobo(o.amount)} · {pv(o.pv)} PV · paid {o.paid_at ?? '—'}
                                        {o.fulfilled_at && ` · collected ${o.fulfilled_at}`}
                                    </p>
                                </div>
                                {o.pickup_code && <PickupCode code={o.pickup_code} />}
                            </CardContent>
                        </Card>
                    ))
                )}
                <Pagination page={orders} />
            </div>
        </AppLayout>
    );
}
