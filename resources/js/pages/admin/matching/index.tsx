import { Badge } from '@/components/ui/badge';
import { DataTable } from '@/components/wf/data-table';
import { Pagination, type Paginated } from '@/components/wf/pagination';
import AdminLayout from '@/layouts/admin-layout';
import { type MatchRunRow } from '@/lib/matching';
import { formatKobo } from '@/lib/money';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin' },
    { title: 'Matching', href: '/admin/matching' },
];

export default function MatchingRuns({ runs }: { runs: Paginated<MatchRunRow> }) {
    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Matching" />
            <div className="space-y-4 p-4">
                <p className="text-muted-foreground max-w-3xl text-sm">
                    Every night at 01:30 (Lagos time) the matching bonus is paid for the day just ended: one match for each 50 PV on a member's
                    smaller leg, up to their package's daily cap. What is left carries forward. Payouts come from the registration ledger's matching
                    line; when it and its contingency fund are short, they are held until money arrives.
                </p>
                <DataTable
                    rows={runs.data}
                    rowKey={(r) => r.id}
                    emptyTitle="No matching runs yet"
                    emptyDescription="The first run happens tonight at 01:30."
                    columns={[
                        {
                            key: 'day',
                            header: 'Day',
                            cell: (r) => (
                                <Link href={route('admin.matching.show', r.id)} className="font-medium underline-offset-4 hover:underline">
                                    {r.day}
                                </Link>
                            ),
                            className: 'whitespace-nowrap',
                        },
                        { key: 'members', header: 'Members paid', align: 'right', cell: (r) => r.members },
                        { key: 'matches', header: 'Matches', align: 'right', cell: (r) => r.matches },
                        { key: 'amount', header: 'Earned', align: 'right', cell: (r) => formatKobo(r.amount), className: 'whitespace-nowrap' },
                        {
                            key: 'held',
                            header: 'Still held',
                            align: 'right',
                            cell: (r) => (r.held > 0 ? <span className="text-warning">{formatKobo(r.held)}</span> : '—'),
                            className: 'whitespace-nowrap',
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
