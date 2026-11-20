import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ReportTabs } from '@/components/wf/report-tabs';
import { StatCard } from '@/components/wf/stat-card';
import AdminLayout from '@/layouts/admin-layout';
import { formatKobo } from '@/lib/money';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

type Line = { label: string; amount: number; count?: number };

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin' },
    { title: 'Reports', href: '/admin/reports/financial' },
];

const periods: [string, string][] = [
    ['today', 'Today'],
    ['month', 'This month'],
    ['last_month', 'Last month'],
    ['quarter', 'This quarter'],
    ['year', 'This year'],
];

function Table({ title, rows, total, counts = false }: { title: string; rows: Line[]; total?: boolean; counts?: boolean }) {
    const sum = rows.reduce((s, r) => s + r.amount, 0);
    return (
        <section className="bg-card shadow-soft overflow-x-auto rounded-xl border">
            <h2 className="border-b px-4 py-3 font-medium">{title}</h2>
            <table className="w-full text-sm">
                <tbody className="divide-y">
                    {rows.length === 0 && (
                        <tr>
                            <td className="text-muted-foreground px-4 py-3">Nothing in this period.</td>
                        </tr>
                    )}
                    {rows.map((r) => (
                        <tr key={r.label}>
                            <td className="px-4 py-2.5">{r.label}</td>
                            {counts && <td className="text-muted-foreground px-4 py-2.5 text-right tabular-nums">{r.count}</td>}
                            <td className="px-4 py-2.5 text-right whitespace-nowrap tabular-nums">{formatKobo(r.amount)}</td>
                        </tr>
                    ))}
                    {total && rows.length > 0 && (
                        <tr className="font-semibold">
                            <td className="px-4 py-2.5">Total</td>
                            {counts && <td />}
                            <td className="px-4 py-2.5 text-right whitespace-nowrap tabular-nums">{formatKobo(sum)}</td>
                        </tr>
                    )}
                </tbody>
            </table>
        </section>
    );
}

export default function Financial({
    period,
    from,
    to,
    moneyIn,
    withdrawals,
    bonuses,
    cooperative,
    members,
}: {
    period: string;
    from: string;
    to: string;
    moneyIn: Line[];
    withdrawals: { count: number; sent: number; charges: number };
    bonuses: Line[];
    cooperative: Line[];
    members: number;
}) {
    const [range, setRange] = useState({ from, to });
    const totalIn = moneyIn.reduce((s, r) => s + r.amount, 0);
    const totalBonuses = bonuses.reduce((s, r) => s + r.amount, 0);

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Financial report" />
            <div className="space-y-6 p-4">
                <ReportTabs current="admin.reports.financial" />
                <div className="flex flex-wrap items-end gap-2">
                    {periods.map(([value, label]) => (
                        <Link
                            key={value}
                            href={route('admin.reports.financial', { period: value })}
                            className={cn(
                                'rounded-md border px-3 py-1.5 text-sm',
                                period === value ? 'bg-accent text-accent-foreground' : 'hover:bg-muted',
                            )}
                        >
                            {label}
                        </Link>
                    ))}
                    <div className="flex flex-wrap items-end gap-2">
                        <div className="grid gap-1">
                            <Label htmlFor="r-from" className="text-xs">
                                From
                            </Label>
                            <Input
                                id="r-from"
                                type="date"
                                className="h-9"
                                value={range.from}
                                onChange={(e) => setRange({ ...range, from: e.target.value })}
                            />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="r-to" className="text-xs">
                                To
                            </Label>
                            <Input
                                id="r-to"
                                type="date"
                                className="h-9"
                                value={range.to}
                                onChange={(e) => setRange({ ...range, to: e.target.value })}
                            />
                        </div>
                        <Button
                            size="sm"
                            variant="secondary"
                            onClick={() => router.get(route('admin.reports.financial'), { period: 'custom', ...range })}
                        >
                            Show
                        </Button>
                    </div>
                </div>
                <p className="text-muted-foreground text-sm">
                    {from} to {to} (Lagos time).
                </p>
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard label="Money in" value={formatKobo(totalIn)} tone="success" />
                    <StatCard label="Withdrawals sent" value={formatKobo(withdrawals.sent)} tone="warning" hint={`${withdrawals.count} paid`} />
                    <StatCard label="Bonuses paid to members" value={formatKobo(totalBonuses)} tone="info" />
                    <StatCard label="New members" value={members.toLocaleString('en-NG')} />
                </div>
                <div className="grid gap-4 lg:grid-cols-2">
                    <Table title="Money in, by way of paying" rows={moneyIn} total counts />
                    <Table title="Bonuses paid, by kind" rows={bonuses} total />
                    <Table title="The cooperative's own income (charges)" rows={cooperative} total />
                </div>
            </div>
        </AdminLayout>
    );
}
