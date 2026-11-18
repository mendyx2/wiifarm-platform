import { Badge } from '@/components/ui/badge';
import { DataTable } from '@/components/wf/data-table';
import { Pagination, type Paginated } from '@/components/wf/pagination';
import AdminLayout from '@/layouts/admin-layout';
import { formatKobo } from '@/lib/money';
import { pv } from '@/lib/network';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

type Run = { id: number; month: string; status: string; team_rule: string; leaders: number; amount: number; held: number };

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin' },
    { title: 'Leadership', href: '/admin/leadership' },
];

export default function LeadershipRuns({ runs, rule, qualifyingPv }: { runs: Paginated<Run>; rule: string; qualifyingPv: number }) {
    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Leadership" />
            <div className="space-y-4 p-4">
                <p className="text-muted-foreground max-w-3xl text-sm">
                    On the first day of each month, every member with {pv(qualifyingPv)} PV on their repurchase PV log earns 5% of what their team
                    earned from matching in the month just ended, from the registration ledger’s leadership line. The team is counted through the{' '}
                    <span className="text-foreground font-medium">{rule}</span>; this is a setting until the board decides (Settings page).
                </p>
                <DataTable
                    rows={runs.data}
                    rowKey={(r) => r.id}
                    emptyTitle="No leadership runs yet"
                    emptyDescription="The first run pays last month, early on the first of the month."
                    columns={[
                        {
                            key: 'month',
                            header: 'Month',
                            cell: (r) => (
                                <Link href={route('admin.leadership.show', r.id)} className="font-medium underline-offset-4 hover:underline">
                                    {r.month}
                                </Link>
                            ),
                        },
                        { key: 'rule', header: 'Team counted through', cell: (r) => r.team_rule },
                        { key: 'leaders', header: 'Leaders', align: 'right', cell: (r) => r.leaders },
                        { key: 'amount', header: 'Earned', align: 'right', cell: (r) => formatKobo(r.amount), className: 'whitespace-nowrap' },
                        {
                            key: 'held',
                            header: 'Still held',
                            align: 'right',
                            cell: (r) => (r.held > 0 ? <span className="text-warning">{formatKobo(r.held)}</span> : '—'),
                        },
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
