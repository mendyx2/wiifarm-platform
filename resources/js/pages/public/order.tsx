import { Badge } from '@/components/ui/badge';
import { DataTable } from '@/components/wf/data-table';
import PublicLayout from '@/layouts/public-layout';
import { formatKobo } from '@/lib/money';
import { Head, router } from '@inertiajs/react';
import { useEffect } from 'react';

type Order = {
    number: string;
    status: 'pending' | 'paid' | 'fulfilled' | 'cancelled';
    amount: number;
    buyer: string | null;
    items: { name: string; quantity: number; price: number }[];
    code: string | null;
    paid_at: string | null;
    fulfilled_at: string | null;
};
type StoreRow = { name: string; state: string; address: string; phone: string };

export default function PublicOrder({ order, waiting, stores }: { order: Order; waiting: boolean; stores: StoreRow[] }) {
    // While Paystack has not confirmed yet, look again every few seconds.
    useEffect(() => {
        if (!waiting) return;
        const t = setInterval(() => router.reload({ only: ['order', 'waiting', 'stores'] }), 5000);
        return () => clearInterval(t);
    }, [waiting]);

    return (
        <PublicLayout>
            <Head title={`Order ${order.number}`} />
            <div className="max-w-3xl space-y-6">
                <div>
                    <h1 className="text-2xl font-semibold">Order {order.number}</h1>
                    <p className="text-muted-foreground text-sm">Keep this page: its link is your own. {order.buyer && `For ${order.buyer}.`}</p>
                </div>

                {order.status === 'paid' && order.code && (
                    <section className="bg-accent text-accent-foreground rounded-xl p-5">
                        <p className="text-sm">Paid. Your pickup code:</p>
                        <p className="mt-1 font-mono text-3xl font-semibold tracking-widest">{order.code}</p>
                        <p className="mt-2 text-sm">
                            Show it at any Wiifarm stockist below to collect the whole order. It never expires and works once. Do not share it with
                            anyone else.
                        </p>
                    </section>
                )}
                {order.status === 'fulfilled' && (
                    <p className="rounded-xl border p-4 text-sm">
                        <Badge variant="success">Collected</Badge> on {order.fulfilled_at}. Thank you.
                    </p>
                )}
                {order.status === 'pending' && (
                    <p className="rounded-xl border p-4 text-sm">
                        {waiting ? 'Waiting for Paystack to confirm your payment. This page updates by itself.' : 'Not paid yet.'}
                    </p>
                )}
                {order.status === 'cancelled' && <p className="rounded-xl border p-4 text-sm">This order was not paid in time and was cancelled.</p>}

                <DataTable
                    rows={order.items}
                    rowKey={(i) => i.name}
                    columns={[
                        { key: 'name', header: 'Product', cell: (i) => i.name },
                        { key: 'q', header: 'Quantity', align: 'right', cell: (i) => i.quantity },
                        { key: 'price', header: 'Price', align: 'right', cell: (i) => formatKobo(i.price * i.quantity) },
                    ]}
                />
                <p className="text-right font-medium">Total {formatKobo(order.amount)}</p>

                {stores.length > 0 && order.status === 'paid' && (
                    <section className="space-y-2">
                        <h2 className="font-medium">Where to collect</h2>
                        <DataTable
                            rows={stores}
                            rowKey={(s) => s.name + s.address}
                            columns={[
                                { key: 'name', header: 'Stockist', cell: (s) => s.name },
                                { key: 'where', header: 'Address', cell: (s) => `${s.address}, ${s.state}` },
                                { key: 'phone', header: 'Phone', cell: (s) => s.phone, className: 'whitespace-nowrap' },
                            ]}
                        />
                    </section>
                )}
            </div>
        </PublicLayout>
    );
}
