import { Pagination, type Paginated } from '@/components/wf/pagination';
import { PvLogTable } from '@/components/wf/pv-log-table';
import { PvSummary } from '@/components/wf/pv-summary';
import AdminLayout from '@/layouts/admin-layout';
import { type PvLogRow, type PvTotals } from '@/lib/network';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

interface Props {
    member: { id: number; username: string; name: string };
    pv: PvTotals | null;
    log: Paginated<PvLogRow>;
}

export default function AdminMemberPv({ member, pv, log }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/admin' },
        { title: 'Members', href: '/admin/members' },
        { title: `@${member.username}`, href: `/admin/members/${member.id}` },
        { title: 'PV log', href: `/admin/members/${member.id}/pv` },
    ];

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={`PV: @${member.username}`} />
            <div className="space-y-6 p-4">
                <PvSummary totals={pv} />
                <PvLogTable rows={log.data} />
                <Pagination page={log} />
            </div>
        </AdminLayout>
    );
}
