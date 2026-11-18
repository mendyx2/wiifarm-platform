import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/wf/data-table';
import { Pagination, type Paginated } from '@/components/wf/pagination';
import { WithdrawalStatus, type WithdrawalRow } from '@/components/wf/withdraw-form';
import AdminLayout, { useAdminCan } from '@/layouts/admin-layout';
import { formatKobo } from '@/lib/money';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

type Row = WithdrawalRow & { who: string };

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin' },
    { title: 'Withdrawals', href: '/admin/withdrawals' },
];

const tabs: [string, string][] = [
    ['pending', 'Waiting'],
    ['paid', 'Paid'],
    ['rejected', 'Refused'],
    ['all', 'All'],
];

export default function AdminWithdrawals({ withdrawals, status }: { withdrawals: Paginated<Row>; status: string }) {
    const can = useAdminCan();

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Withdrawals" />
            <div className="space-y-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <nav className="flex flex-wrap gap-2" aria-label="Filter by status">
                        {tabs.map(([value, label]) => (
                            <Link
                                key={value}
                                href={route('admin.withdrawals.index', value === 'pending' ? {} : { status: value })}
                                className={cn(
                                    'rounded-full border px-3 py-1 text-sm',
                                    status === value ? 'bg-primary text-primary-foreground border-primary' : 'hover:bg-muted',
                                )}
                            >
                                {label}
                            </Link>
                        ))}
                    </nav>
                    {can('withdrawals.methods') && (
                        <Button asChild variant="secondary" size="sm">
                            <Link href={route('admin.withdrawals.methods')}>Withdrawal methods</Link>
                        </Button>
                    )}
                </div>
                <DataTable
                    rows={withdrawals.data}
                    rowKey={(w) => w.id}
                    emptyTitle={status === 'pending' ? 'Nothing waiting' : 'No withdrawals here'}
                    columns={[
                        {
                            key: 'id',
                            header: 'Request',
                            cell: (w) => (
                                <Link href={route('admin.withdrawals.show', w.id)} className="group block">
                                    <span className="font-medium group-hover:underline">#{w.id}</span>
                                    <span className="text-muted-foreground block text-xs">{w.who}</span>
                                </Link>
                            ),
                        },
                        { key: 'method', header: 'Paid by', cell: (w) => w.method },
                        { key: 'status', header: 'Status', cell: (w) => <WithdrawalStatus status={w.status} /> },
                        { key: 'payable', header: 'To send', align: 'right', cell: (w) => formatKobo(w.payable) },
                        { key: 'at', header: 'Requested', cell: (w) => w.requested_at, className: 'whitespace-nowrap' },
                    ]}
                />
                <Pagination page={withdrawals} />
            </div>
        </AdminLayout>
    );
}
