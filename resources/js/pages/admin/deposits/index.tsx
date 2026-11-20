import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/wf/data-table';
import { DepositStatus } from '@/components/wf/deposit-status';
import { Pagination, type Paginated } from '@/components/wf/pagination';
import AdminLayout, { useAdminCan } from '@/layouts/admin-layout';
import { formatKobo } from '@/lib/money';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

export type DepositRow = {
    id: number;
    member: string;
    member_id: number;
    method: string;
    amount: number;
    reference: string;
    status: string;
    reason: string | null;
    at: string;
    decided_at: string | null;
    proof_is_pdf: boolean;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin' },
    { title: 'Deposits', href: '/admin/deposits' },
];

const tabs: [string, string][] = [
    ['pending', 'Waiting'],
    ['approved', 'Approved'],
    ['rejected', 'Refused'],
    ['all', 'All'],
];

export default function AdminDeposits({ deposits, status }: { deposits: Paginated<DepositRow>; status: string }) {
    const can = useAdminCan();

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Deposits" />
            <div className="space-y-4 p-4">
                <p className="text-muted-foreground max-w-3xl text-sm">
                    Deposits by bank transfer. Check that the money arrived in Wiifarm's account with the member's reference, then approve: the amount
                    goes to their main balance. Paystack deposits are added by themselves and are under Online payments.
                </p>
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <nav className="flex flex-wrap gap-2" aria-label="Filter by status">
                        {tabs.map(([value, label]) => (
                            <Link
                                key={value}
                                href={route('admin.deposits.index', value === 'pending' ? {} : { status: value })}
                                className={cn(
                                    'rounded-full border px-3 py-1 text-sm',
                                    status === value ? 'bg-primary text-primary-foreground border-primary' : 'hover:bg-muted',
                                )}
                            >
                                {label}
                            </Link>
                        ))}
                    </nav>
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="secondary" size="sm">
                            <Link href={route('admin.usdt.index')}>USDT deposits</Link>
                        </Button>
                        {can('deposits.methods') && (
                            <Button asChild variant="secondary" size="sm">
                                <Link href={route('admin.deposits.methods')}>Deposit methods</Link>
                            </Button>
                        )}
                    </div>
                </div>
                <DataTable
                    rows={deposits.data}
                    rowKey={(d) => d.id}
                    emptyTitle={status === 'pending' ? 'Nothing waiting' : 'No deposits here'}
                    columns={[
                        {
                            key: 'id',
                            header: 'Deposit',
                            cell: (d) => (
                                <Link href={route('admin.deposits.show', d.id)} className="group block">
                                    <span className="font-medium group-hover:underline">#{d.id}</span>
                                    <span className="text-muted-foreground block text-xs">@{d.member}</span>
                                </Link>
                            ),
                        },
                        { key: 'ref', header: 'Reference', cell: (d) => d.reference },
                        { key: 'status', header: 'Status', cell: (d) => <DepositStatus status={d.status} /> },
                        { key: 'amount', header: 'Amount', align: 'right', cell: (d) => formatKobo(d.amount) },
                        { key: 'at', header: 'Sent', cell: (d) => d.at, className: 'whitespace-nowrap' },
                    ]}
                />
                <Pagination page={deposits} />
            </div>
        </AdminLayout>
    );
}
