import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DataTable } from '@/components/wf/data-table';
import AdminLayout from '@/layouts/admin-layout';
import { formatKobo } from '@/lib/money';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Factory, ShieldCheck } from 'lucide-react';

type Line = { key: string; label: string; contingency: boolean; balance: number; in: number; out: number; held_count: number; held_amount: number };
type Pool = { code: string; name: string; lines: Line[]; balance: number; in: number; out: number };
type Manufacturer = { id: number; name: string; balance: number; in: number; out: number };
type Held = { id: number; pool: string; line: string; to: string; amount: number; memo: string | null; since: string };

interface PoolsProps {
    period: 'today' | 'month' | 'quarter' | 'all';
    schedule: { version: number; source: string; effective_from: string };
    pools: Pool[];
    manufacturers: Manufacturer[];
    held: Held[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin' },
    { title: 'Pools', href: '/admin/pools' },
];

const periods: [PoolsProps['period'], string][] = [
    ['today', 'Today'],
    ['month', 'This month'],
    ['quarter', 'This quarter'],
    ['all', 'All time'],
];

function PoolCard({ pool, periodLabel }: { pool: Pool; periodLabel: string }) {
    const heldTotal = pool.lines.reduce((sum, l) => sum + l.held_amount, 0);

    return (
        <Card>
            <CardHeader className="pb-3">
                <div className="flex flex-wrap items-baseline justify-between gap-2">
                    <CardTitle className="text-base">{pool.name}</CardTitle>
                    <span className="text-sm tabular-nums">
                        holds <b>{formatKobo(pool.balance)}</b>
                    </span>
                </div>
                {heldTotal > 0 && (
                    <Badge variant="warning" className="w-fit">
                        {formatKobo(heldTotal)} held, waiting for money
                    </Badge>
                )}
            </CardHeader>
            <CardContent className="overflow-x-auto pt-0">
                <table className="w-full text-sm">
                    <thead className="text-muted-foreground">
                        <tr>
                            <th className="py-2 text-left font-medium">Line</th>
                            <th className="py-2 pl-3 text-right font-medium">In ({periodLabel})</th>
                            <th className="py-2 pl-3 text-right font-medium">Out ({periodLabel})</th>
                            <th className="py-2 pl-3 text-right font-medium">Holds now</th>
                        </tr>
                    </thead>
                    <tbody>
                        {pool.lines.map((l) => (
                            <tr key={l.key} className={cn('border-t', l.contingency && 'bg-muted/50 font-medium')}>
                                <td className="py-2 pr-3">
                                    {l.contingency && <ShieldCheck className="text-success mr-1 inline size-3.5" aria-hidden />}
                                    {l.label}
                                    {l.held_count > 0 && (
                                        <span className="text-warning block text-xs">
                                            {l.held_count} held · {formatKobo(l.held_amount)}
                                        </span>
                                    )}
                                </td>
                                <td className="py-2 pl-3 text-right whitespace-nowrap tabular-nums">{formatKobo(l.in)}</td>
                                <td className="py-2 pl-3 text-right whitespace-nowrap tabular-nums">{formatKobo(l.out)}</td>
                                <td className="py-2 pl-3 text-right whitespace-nowrap tabular-nums">{formatKobo(l.balance)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </CardContent>
        </Card>
    );
}

export default function Pools({ period, schedule, pools, manufacturers, held }: PoolsProps) {
    const periodLabel = periods.find(([p]) => p === period)?.[1].toLowerCase() ?? '';

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Pools" />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <p className="text-muted-foreground max-w-3xl text-sm">
                        Each pool is its own ledger with its own contingency fund; no pool pays another pool's bonuses. Split by allocation schedule v
                        {schedule.version} ({schedule.source}), in force since {schedule.effective_from}.
                    </p>
                    <nav className="flex flex-wrap gap-2" aria-label="Period">
                        {periods.map(([value, label]) => (
                            <Link
                                key={value}
                                href={route('admin.pools', value === 'month' ? {} : { period: value })}
                                preserveScroll
                                className={cn(
                                    'rounded-full border px-3 py-1 text-sm',
                                    period === value ? 'bg-primary text-primary-foreground border-primary' : 'hover:bg-muted',
                                )}
                                aria-current={period === value ? 'page' : undefined}
                            >
                                {label}
                            </Link>
                        ))}
                    </nav>
                </div>

                <section className="space-y-2">
                    <h2 className="font-medium">Manufacturing partners</h2>
                    <p className="text-muted-foreground text-sm">Separate companies from the cooperative, each with its own account.</p>
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {manufacturers.map((m) => (
                            <Card key={m.id} className="py-0">
                                <CardContent className="flex items-start gap-4 p-5">
                                    <span className="bg-secondary flex size-10 shrink-0 items-center justify-center rounded-lg">
                                        <Factory className="size-5" aria-hidden />
                                    </span>
                                    <div className="min-w-0">
                                        <p className="text-muted-foreground text-sm">{m.name}</p>
                                        <p className="text-2xl font-semibold tabular-nums">{formatKobo(m.balance)}</p>
                                        <p className="text-muted-foreground text-xs">
                                            {formatKobo(m.in)} in {periodLabel}
                                        </p>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                </section>

                <section className="space-y-2">
                    <h2 className="font-medium">The cooperative's pools</h2>
                    <div className="grid gap-4 xl:grid-cols-2">
                        {pools
                            .filter((p) => p.code !== 'P')
                            .map((p) => (
                                <PoolCard key={p.code} pool={p} periodLabel={periodLabel} />
                            ))}
                    </div>
                </section>

                <section className="space-y-2">
                    <h2 className="font-medium">Held for public-shop pickups</h2>
                    <p className="text-muted-foreground text-sm">
                        Public sales stay outside the cooperative. The affiliate's share and the stockist's rebate wait here until the buyer collects.
                    </p>
                    <div className="grid gap-4 xl:grid-cols-2">
                        {pools
                            .filter((p) => p.code === 'P')
                            .map((p) => (
                                <PoolCard key={p.code} pool={p} periodLabel={periodLabel} />
                            ))}
                    </div>
                </section>

                <section className="space-y-2">
                    <h2 className="font-medium">Held payouts</h2>
                    <p className="text-muted-foreground text-sm">
                        Payouts their line and its contingency fund could not cover yet. Each is paid in full as soon as money arrives, oldest first.
                    </p>
                    <DataTable
                        rows={held}
                        rowKey={(h) => h.id}
                        emptyTitle="Nothing is held"
                        emptyDescription="Every payout so far has been covered by its pool."
                        columns={[
                            { key: 'since', header: 'Held since', cell: (h) => h.since, className: 'whitespace-nowrap' },
                            { key: 'line', header: 'Pool line', cell: (h) => `${h.pool} · ${h.line}` },
                            { key: 'to', header: 'To', cell: (h) => h.to },
                            { key: 'memo', header: 'Reason', cell: (h) => h.memo ?? '—' },
                            { key: 'amount', header: 'Amount', align: 'right', cell: (h) => formatKobo(h.amount) },
                        ]}
                    />
                </section>
            </div>
        </AdminLayout>
    );
}
