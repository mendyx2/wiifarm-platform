import { KycBadge } from '@/components/admin/badges';
import { DataTable, type Column } from '@/components/wf/data-table';
import { Pagination, type Paginated } from '@/components/wf/pagination';
import AdminLayout from '@/layouts/admin-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

type Row = { id: number; status: string; member: { id: number; username: string; name: string }; submitted_at: string; reviewed_at: string | null };

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin' },
    { title: 'KYC review', href: '/admin/kyc' },
];

const tabs: [string, string][] = [
    ['pending', 'Waiting for review'],
    ['approved', 'Approved'],
    ['rejected', 'Rejected'],
    ['all', 'All'],
];

const columns: Column<Row>[] = [
    {
        key: 'member',
        header: 'Member',
        cell: (s) => (
            <>
                <span className="font-medium">{s.member.name}</span>
                <span className="text-muted-foreground block text-xs">@{s.member.username}</span>
            </>
        ),
    },
    { key: 'submitted', header: 'Submitted', cell: (s) => s.submitted_at, className: 'whitespace-nowrap' },
    { key: 'status', header: 'Result', cell: (s) => <KycBadge status={s.status} short /> },
    {
        key: 'open',
        header: '',
        align: 'right',
        cell: (s) => (
            <Link href={route('admin.kyc.show', s.id)} className="text-primary font-medium underline underline-offset-4">
                {s.status === 'pending' ? 'Review' : 'View'}
            </Link>
        ),
    },
];

export default function KycIndex({ submissions, status }: { submissions: Paginated<Row>; status: string }) {
    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="KYC review" />
            <div className="space-y-4 p-4">
                <nav className="flex flex-wrap gap-2" aria-label="Filter by result">
                    {tabs.map(([value, label]) => (
                        <Link
                            key={value}
                            href={route('admin.kyc.index', value === 'pending' ? {} : { status: value })}
                            className={cn(
                                'rounded-full border px-3 py-1 text-sm',
                                status === value ? 'bg-primary text-primary-foreground border-primary' : 'hover:bg-muted',
                            )}
                            aria-current={status === value ? 'page' : undefined}
                        >
                            {label}
                        </Link>
                    ))}
                </nav>
                <DataTable
                    columns={columns}
                    rows={submissions.data}
                    rowKey={(s) => s.id}
                    emptyTitle={status === 'pending' ? 'Nothing waiting for review' : 'No submissions here'}
                    emptyDescription={status === 'pending' ? 'New submissions appear here, oldest first.' : undefined}
                />
                <Pagination page={submissions} />
            </div>
        </AdminLayout>
    );
}
