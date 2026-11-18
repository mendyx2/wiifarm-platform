import { Badge } from '@/components/ui/badge';
import { DataTable } from '@/components/wf/data-table';
import { Pagination, type Paginated } from '@/components/wf/pagination';
import AdminLayout from '@/layouts/admin-layout';
import { formatKobo } from '@/lib/money';
import { pv } from '@/lib/network';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

type Run = {
    id: number;
    month: string;
    status: string;
    activity_required: boolean;
    repurchase_pv: number;
    earners: number;
    amount: number;
    unpaid: number;
    held: number;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin' },
    { title: 'Unilevel', href: '/admin/unilevel' },
];

export default function UnilevelRuns({ runs, rates }: { runs: Paginated<Run>; rates: string[] }) {
    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Unilevel" />
            <div className="space-y-4 p-4">
                <p className="text-muted-foreground max-w-3xl text-sm">
                    On the first of each month, the 10 generations above every member who bought products earn a share of its PV value:{' '}
                    {rates.map((r) => `${Number(r)}%`).join(', ')}, each only as deep as their package reaches. Paid from the repurchase ledger’s
                    unilevel line; shares nobody can receive go to its contingency fund.
                </p>
                <DataTable
                    rows={runs.data}
                    rowKey={(r) => r.id}
                    emptyTitle="No unilevel runs yet"
                    emptyDescription="The first run pays last month, early on the first of the month."
                    columns={[
                        {
                            key: 'month',
                            header: 'Month',
                            cell: (r) => (
                                <Link href={route('admin.unilevel.show', r.id)} className="font-medium underline-offset-4 hover:underline">
                                    {r.month}
                                </Link>
                            ),
                        },
                        {
                            key: 'pv',
                            header: 'Repurchase PV',
                            align: 'right',
                            cell: (r) => `${pv(r.repurchase_pv)} PV`,
                            className: 'whitespace-nowrap',
                        },
                        { key: 'earners', header: 'Members paid', align: 'right', cell: (r) => r.earners },
                        { key: 'amount', header: 'Earned', align: 'right', cell: (r) => formatKobo(r.amount), className: 'whitespace-nowrap' },
                        {
                            key: 'unpaid',
                            header: 'To contingency',
                            align: 'right',
                            cell: (r) => formatKobo(r.unpaid),
                            className: 'whitespace-nowrap',
                        },
                        {
                            key: 'held',
                            header: 'Still held',
                            align: 'right',
                            cell: (r) => (r.held > 0 ? <span className="text-warning">{formatKobo(r.held)}</span> : '—'),
                        },
                        { key: 'rule', header: '10 PV rule', cell: (r) => (r.activity_required ? 'On' : 'Off') },
                        {
                            key: 'status',
                            header: 'Run',
                            cell: (r) => (r.status === 'done' ? <Badge variant="success">Done</Badge> : <Badge variant="warning">Running</Badge>),
                        },
                    ]}
                />
                <Pagination page={runs} />
            </div>
        </AdminLayout>
    );
}
