import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { DataTable } from '@/components/wf/data-table';
import { OrderStatusBadge } from '@/components/wf/order-badges';
import { type Paginated } from '@/components/wf/pagination';
import { StatementTable } from '@/components/wf/statement-table';
import AffiliateLayout from '@/layouts/affiliate-layout';
import { type StatementRow } from '@/lib/ledger';
import { formatKobo } from '@/lib/money';
import { Head, Link } from '@inertiajs/react';
import { Copy } from 'lucide-react';
import { useState } from 'react';

interface Props {
    affiliate: { name: string; code: string; link: string };
    wallet: number;
    statement: Paginated<StatementRow>;
    orders: { id: number; number: string; status: string; units: number; commission: number; paid_at: string | null }[];
}

export default function AffiliateDashboard({ affiliate, wallet, statement, orders }: Props) {
    const [copied, setCopied] = useState(false);
    const copy = () =>
        navigator.clipboard.writeText(affiliate.link).then(
            () => setCopied(true),
            () => setCopied(false),
        );
    const waiting = orders.filter((o) => o.status === 'paid').reduce((sum, o) => sum + o.commission, 0);

    return (
        <AffiliateLayout>
            <Head title="Affiliate dashboard" />
            <div className="space-y-6 p-4">
                <div className="grid gap-4 md:grid-cols-3">
                    <Card className="py-0 md:col-span-2">
                        <CardContent className="grid gap-2 p-5">
                            <p className="text-muted-foreground text-sm">Your link · code {affiliate.code}</p>
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="bg-muted rounded px-2 py-1 font-mono text-sm break-all select-all">{affiliate.link}</span>
                                <Button size="sm" variant="secondary" onClick={copy}>
                                    <Copy className="size-4" />
                                    {copied ? 'Copied' : 'Copy'}
                                </Button>
                            </div>
                            <p className="text-muted-foreground text-xs">
                                Share it anywhere. You earn 20% of the margin (₦100 a unit) on every public-shop sale it brings, paid when the buyer
                                collects.
                            </p>
                        </CardContent>
                    </Card>
                    <Card className="py-0">
                        <CardContent className="grid gap-1 p-5">
                            <p className="text-muted-foreground text-sm">Affiliate wallet</p>
                            <p className="text-3xl font-semibold tabular-nums">{formatKobo(wallet)}</p>
                            <p className="text-muted-foreground text-xs">{formatKobo(waiting)} more waiting for buyers to collect</p>
                            <Button asChild size="sm" variant="secondary" className="mt-2 w-fit">
                                <Link href={route('affiliate.withdraw')}>Withdraw</Link>
                            </Button>
                        </CardContent>
                    </Card>
                </div>

                <section className="space-y-2">
                    <h2 className="font-medium">Sales through your link</h2>
                    <DataTable
                        rows={orders}
                        rowKey={(o) => o.id}
                        emptyTitle="No sales yet"
                        emptyDescription="Sales appear here once someone buys through your link."
                        columns={[
                            { key: 'date', header: 'Paid', cell: (o) => o.paid_at ?? '—', className: 'whitespace-nowrap' },
                            { key: 'number', header: 'Order', cell: (o) => o.number },
                            { key: 'status', header: 'Status', cell: (o) => <OrderStatusBadge status={o.status} /> },
                            { key: 'units', header: 'Units', align: 'right', cell: (o) => o.units },
                            { key: 'commission', header: 'Your commission', align: 'right', cell: (o) => formatKobo(o.commission) },
                        ]}
                    />
                </section>

                <section className="space-y-2">
                    <h2 className="font-medium">Wallet statement</h2>
                    <StatementTable rows={statement.data} />
                </section>
            </div>
        </AffiliateLayout>
    );
}
