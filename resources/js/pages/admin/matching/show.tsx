import { MatchTable } from '@/components/wf/match-table';
import { Pagination, type Paginated } from '@/components/wf/pagination';
import { StatCard } from '@/components/wf/stat-card';
import AdminLayout from '@/layouts/admin-layout';
import { type MatchRow, type MatchRunRow } from '@/lib/matching';
import { formatKobo } from '@/lib/money';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

type Line = MatchRow & { member: { id: number; username: string; name: string } };

export default function MatchingRun({ run, lines }: { run: MatchRunRow; lines: Paginated<Line> }) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/admin' },
        { title: 'Matching', href: '/admin/matching' },
        { title: run.day, href: `/admin/matching/${run.id}` },
    ];

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={`Matching ${run.day}`} />
            <div className="space-y-4 p-4">
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard label="Members paid" value={String(run.members)} />
                    <StatCard label="Matches" value={String(run.matches)} />
                    <StatCard label="Earned" value={formatKobo(run.amount)} />
                    <StatCard label="Still held" value={formatKobo(run.held)} tone={run.held > 0 ? 'warning' : 'default'} />
                </div>
                <MatchTable
                    rows={lines.data}
                    emptyTitle="Nobody had a match this day"
                    lead={[
                        {
                            key: 'member',
                            header: 'Member',
                            cell: (m) => (
                                <Link href={route('admin.members.show', m.member.id)} className="underline-offset-4 hover:underline">
                                    <span className="font-medium">{m.member.name || m.member.username}</span>{' '}
                                    <span className="text-muted-foreground text-xs">@{m.member.username}</span>
                                </Link>
                            ),
                        },
                    ]}
                />
                <Pagination page={lines} />
            </div>
        </AdminLayout>
    );
}
