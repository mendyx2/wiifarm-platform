import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { FormField, selectClass } from '@/components/wf/form-field';
import { Pagination, type Paginated } from '@/components/wf/pagination';
import { ReportTabs } from '@/components/wf/report-tabs';
import AdminLayout from '@/layouts/admin-layout';
import { formatKobo } from '@/lib/money';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

type Tx = {
    id: number;
    type: string;
    memo: string | null;
    by_admin: boolean;
    at: string;
    lines: { account: string; amount: number; currency: string }[];
};
type Filters = { type: string; member: string; from: string; to: string };

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin' },
    { title: 'Reports', href: '/admin/reports/financial' },
    { title: 'Transactions', href: '/admin/reports/transactions' },
];

function amount(value: number, currency: string) {
    if (currency === 'PV') return `${(value / 100).toLocaleString('en-NG')} PV`;
    if (currency === 'NGN') return formatKobo(value);
    return value.toLocaleString('en-NG');
}

export default function Transactions({ filters, types, transactions }: { filters: Filters; types: string[]; transactions: Paginated<Tx> }) {
    const [f, setF] = useState(filters);

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Transactions" />
            <div className="space-y-4 p-4">
                <ReportTabs current="admin.reports.transactions" />
                <p className="text-muted-foreground max-w-3xl text-sm">
                    Every ledger transaction, newest first. Each one moves money (or PV) between accounts and always adds up to zero.
                </p>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        router.get(route('admin.reports.transactions'), Object.fromEntries(Object.entries(f).filter(([, v]) => v)));
                    }}
                    className="flex flex-wrap items-end gap-3"
                >
                    <FormField id="t-type" label="Kind">
                        <select id="t-type" className={selectClass} value={f.type} onChange={(e) => setF({ ...f, type: e.target.value })}>
                            <option value="">All</option>
                            {types.map((t) => (
                                <option key={t} value={t}>
                                    {t}
                                </option>
                            ))}
                        </select>
                    </FormField>
                    <FormField id="t-member" label="Member (username)">
                        <Input id="t-member" className="w-44" value={f.member} onChange={(e) => setF({ ...f, member: e.target.value })} />
                    </FormField>
                    <FormField id="t-from" label="From">
                        <Input id="t-from" type="date" value={f.from} onChange={(e) => setF({ ...f, from: e.target.value })} />
                    </FormField>
                    <FormField id="t-to" label="To">
                        <Input id="t-to" type="date" value={f.to} onChange={(e) => setF({ ...f, to: e.target.value })} />
                    </FormField>
                    <Button variant="secondary">Filter</Button>
                </form>
                <ul className="space-y-2">
                    {transactions.data.length === 0 && <li className="text-muted-foreground text-sm">No transactions match.</li>}
                    {transactions.data.map((tx) => (
                        <li key={tx.id} className="bg-card shadow-soft rounded-xl border p-3 text-sm">
                            <div className="flex flex-wrap items-baseline justify-between gap-2">
                                <p className="font-medium">
                                    #{tx.id} · {tx.type}
                                    {tx.by_admin && <span className="text-muted-foreground font-normal"> · by an admin</span>}
                                </p>
                                <span className="text-muted-foreground text-xs">{tx.at}</span>
                            </div>
                            {tx.memo && <p className="text-muted-foreground text-xs">{tx.memo}</p>}
                            <table className="mt-2 w-full text-xs">
                                <tbody>
                                    {tx.lines.map((l, i) => (
                                        <tr key={i}>
                                            <td className="py-0.5 pr-2">{l.account}</td>
                                            <td
                                                className={cn(
                                                    'py-0.5 text-right whitespace-nowrap tabular-nums',
                                                    l.amount < 0 ? 'text-destructive' : 'text-success',
                                                )}
                                            >
                                                {l.amount > 0 ? '+' : ''}
                                                {amount(l.amount, l.currency)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </li>
                    ))}
                </ul>
                <Pagination page={transactions} />
            </div>
        </AdminLayout>
    );
}
