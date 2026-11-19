import { DataTable } from '@/components/wf/data-table';
import { LeadershipStatus } from '@/components/wf/leadership-status';
import { Pagination, type Paginated } from '@/components/wf/pagination';
import { StatCard } from '@/components/wf/stat-card';
import AdminLayout from '@/layouts/admin-layout';
import { formatKobo } from '@/lib/money';
import { pv } from '@/lib/network';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

type Run = { id: number; month: string; team_rule: string; leaders: number; amount: number; held: number };
type Line = {
    id: number;
    lesser_leg_pv: number;
    team_members: number;
    team_matching: number;
    amount: number;
    status: string;
    member: { id: number; username: string; name: string };
};

export default function LeadershipRun({ run, lines }: { run: Run; lines: Paginated<Line> }) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/admin' },
        { title: 'Leadership', href: '/admin/leadership' },
        { title: run.month, href: `/admin/leadership/${run.id}` },
    ];

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={`Leadership ${run.month}`} />
            <div className="space-y-4 p-4">
                <p className="text-muted-foreground text-sm">Team counted through the {run.team_rule}.</p>
                <div className="grid gap-4 sm:grid-cols-3">
                    <StatCard label="Leaders" value={String(run.leaders)} />
                    <StatCard label="Earned" value={formatKobo(run.amount)} />
                    <StatCard label="Still held" value={formatKobo(run.held)} tone={run.held > 0 ? 'warning' : 'default'} />
                </div>
                <DataTable
                    rows={lines.data}
                    rowKey={(l) => l.id}
                    emptyTitle="Nobody qualified this month"
                    columns={[
                        {
                            key: 'member',
                            header: 'Leader',
                            cell: (l) => (
                                <Link href={route('admin.members.show', l.member.id)} className="underline-offset-4 hover:underline">
                                    <span className="font-medium">{l.member.name || l.member.username}</span>{' '}
                                    <span className="text-muted-foreground text-xs">@{l.member.username}</span>
                                </Link>
                            ),
                        },
                        {
                            key: 'pv',
                            header: 'Repurchase PV',
                            align: 'right',
                            cell: (l) => `${pv(l.lesser_leg_pv)} PV`,
                            className: 'whitespace-nowrap',
                        },
                        { key: 'team', header: 'Team members', align: 'right', cell: (l) => l.team_members },
                        {
                            key: 'tm',
                            header: 'Team matching',
                            align: 'right',
                            cell: (l) => formatKobo(l.team_matching),
                            className: 'whitespace-nowrap',
                        },
                        { key: 'amount', header: 'Earned (5%)', align: 'right', cell: (l) => formatKobo(l.amount), className: 'whitespace-nowrap' },
                        { key: 'status', header: 'Status', cell: (l) => <LeadershipStatus status={l.status} /> },
                    ]}
                />
                <Pagination page={lines} />
            </div>
        </AdminLayout>
    );
}
