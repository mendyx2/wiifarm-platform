import { Pagination, type Paginated } from '@/components/wf/pagination';
import { StatementTable } from '@/components/wf/statement-table';
import AdminLayout from '@/layouts/admin-layout';
import { type StatementRow } from '@/lib/ledger';
import { formatKobo } from '@/lib/money';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

interface Props {
    member: { id: number; username: string; name: string };
    wallet: string;
    label: string;
    balance: number;
    entries: Paginated<StatementRow>;
}

export default function MemberWallet({ member, wallet, label, balance, entries }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/admin' },
        { title: 'Members', href: '/admin/members' },
        { title: `@${member.username}`, href: `/admin/members/${member.id}` },
        { title: label, href: `/admin/members/${member.id}/wallets/${wallet}` },
    ];

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={`${label}: @${member.username}`} />
            <div className="space-y-4 p-4">
                <div>
                    <p className="text-muted-foreground text-sm">
                        {member.name} · {label}
                    </p>
                    <p className="text-3xl font-semibold tabular-nums">{formatKobo(balance)}</p>
                </div>
                <StatementTable rows={entries.data} showTransaction />
                <Pagination page={entries} />
            </div>
        </AdminLayout>
    );
}
