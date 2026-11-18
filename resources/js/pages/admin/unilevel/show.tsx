import { Badge } from '@/components/ui/badge';
import { DataTable } from '@/components/wf/data-table';
import { Pagination, type Paginated } from '@/components/wf/pagination';
import { StatCard } from '@/components/wf/stat-card';
import AdminLayout from '@/layouts/admin-layout';
import { formatKobo } from '@/lib/money';
import { pv } from '@/lib/network';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

type Run = {
    id: number;
    month: string;
    activity_required: boolean;
    repurchase_pv: number;
    earners: number;
    amount: number;
    unpaid: number;
    held: number;
};
type Line = {
    id: number;
    amount: number;
    status: string;
    by_generation: { generation: number; amount: number }[];
    member: { id: number; username: string; name: string; package: string | null };
};

export default function UnilevelRun({ run, lines }: { run: Run; lines: Paginated<Line> }) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/admin' },
        { title: 'Unilevel', href: '/admin/unilevel' },
        { title: run.month, href: `/admin/unilevel/${run.id}` },
    ];

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={`Unilevel ${run.month}`} />
            <div className="space-y-4 p-4">
                <p className="text-muted-foreground text-sm">
                    {pv(run.repurchase_pv)} PV bought by members this month. The 10 PV rule was {run.activity_required ? 'on' : 'off'}.
                </p>
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard label="Members paid" value={String(run.earners)} />
                    <StatCard label="Earned" value={formatKobo(run.amount)} />
                    <StatCard label="To the contingency fund" value={formatKobo(run.unpaid)} hint="Shares nobody could receive" />
                    <StatCard label="Still held" value={formatKobo(run.held)} tone={run.held > 0 ? 'warning' : 'default'} />
                </div>
                <DataTable
                    rows={lines.data}
                    rowKey={(l) => l.id}
                    emptyTitle="Nobody earned unilevel this month"
                    columns={[
                        {
                            key: 'member',
                            header: 'Member',
                            cell: (l) => (
                                <Link href={route('admin.members.show', l.member.id)} className="underline-offset-4 hover:underline">
                                    <span className="font-medium">{l.member.name || l.member.username}</span>{' '}
                                    <span className="text-muted-foreground text-xs">
                                        @{l.member.username}
                                        {l.member.package && ` · ${l.member.package}`}
                                    </span>
                                </Link>
                            ),
                        },
                        {
                            key: 'gens',
                            header: 'By generation',
                            cell: (l) => (
                                <span className="text-muted-foreground text-xs">
                                    {l.by_generation.map((g) => `G${g.generation} ${formatKobo(g.amount)}`).join(' · ')}
                                </span>
                            ),
                        },
                        { key: 'amount', header: 'Earned', align: 'right', cell: (l) => formatKobo(l.amount), className: 'whitespace-nowrap' },
                        {
                            key: 'status',
                            header: 'Status',
                            cell: (l) =>
                                l.status === 'paid' ? (
                                    <Badge variant="success">Paid</Badge>
                                ) : (
                                    <Badge variant="warning">Held until the line refills</Badge>
                                ),
                        },
                    ]}
                />
                <Pagination page={lines} />
            </div>
        </AdminLayout>
    );
}
