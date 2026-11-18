import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { DataTable } from '@/components/wf/data-table';
import { Pagination, type Paginated } from '@/components/wf/pagination';
import { PaymentStatus, type PaymentRow } from '@/components/wf/payment-history';
import AdminLayout from '@/layouts/admin-layout';
import { formatKobo } from '@/lib/money';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

type Row = PaymentRow & {
    gateway_status: string | null;
    channel: string | null;
    paid_at: string | null;
    can_check: boolean;
    member: { id: number; username: string; name: string };
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin' },
    { title: 'Online payments', href: '/admin/payments' },
];

const tabs: [string, string][] = [
    ['all', 'All'],
    ['pending', 'Waiting'],
    ['paid', 'Paid'],
    ['failed', 'Not completed'],
    ['mismatch', 'Wrong amount'],
];

export default function AdminPayments({ payments, status, q }: { payments: Paginated<Row>; status: string; q: string }) {
    const [search, setSearch] = useState(q);
    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        router.get(route('admin.payments.index'), { ...(status !== 'all' && { status }), ...(search && { q: search }) }, { preserveState: true });
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Online payments" />
            <div className="space-y-4 p-4">
                <p className="text-muted-foreground max-w-3xl text-sm">
                    Payments made through Paystack. Only Paystack can confirm a payment: “Ask Paystack” checks one that is still waiting, in case its
                    message did not arrive. A payment with the wrong amount is never credited; look into it with Paystack.
                </p>
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <nav className="flex flex-wrap gap-2" aria-label="Filter by status">
                        {tabs.map(([value, label]) => (
                            <Link
                                key={value}
                                href={route('admin.payments.index', value === 'all' ? {} : { status: value })}
                                className={cn(
                                    'rounded-full border px-3 py-1 text-sm',
                                    status === value ? 'bg-primary text-primary-foreground border-primary' : 'hover:bg-muted',
                                )}
                            >
                                {label}
                            </Link>
                        ))}
                    </nav>
                    <form onSubmit={submit} className="flex gap-2">
                        <Input
                            aria-label="Search payments"
                            placeholder="Reference or username"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="w-56"
                        />
                        <Button type="submit" variant="secondary">
                            <Search className="size-4" />
                            Search
                        </Button>
                    </form>
                </div>
                <DataTable
                    rows={payments.data}
                    rowKey={(p) => p.id}
                    emptyTitle="No online payments"
                    columns={[
                        { key: 'at', header: 'Started', cell: (p) => p.at, className: 'whitespace-nowrap' },
                        {
                            key: 'member',
                            header: 'Member',
                            cell: (p) => (
                                <Link href={route('admin.members.show', p.member.id)} className="underline-offset-4 hover:underline">
                                    <span className="font-medium">{p.member.name || p.member.username}</span>{' '}
                                    <span className="text-muted-foreground text-xs">@{p.member.username}</span>
                                </Link>
                            ),
                        },
                        {
                            key: 'what',
                            header: 'For',
                            cell: (p) => (
                                <>
                                    {p.what}
                                    <span className="text-muted-foreground block font-mono text-xs">{p.reference}</span>
                                    {p.note && <span className="text-muted-foreground block text-xs">{p.note}</span>}
                                </>
                            ),
                        },
                        { key: 'amount', header: 'Amount', align: 'right', cell: (p) => formatKobo(p.amount), className: 'whitespace-nowrap' },
                        {
                            key: 'status',
                            header: 'Status',
                            cell: (p) => (
                                <>
                                    <PaymentStatus status={p.status} />
                                    {(p.gateway_status || p.channel) && (
                                        <span className="text-muted-foreground block text-xs">
                                            Paystack: {[p.gateway_status, p.channel].filter(Boolean).join(', ')}
                                        </span>
                                    )}
                                </>
                            ),
                        },
                        {
                            key: 'act',
                            header: '',
                            cell: (p) =>
                                p.can_check && (
                                    <Button
                                        size="sm"
                                        variant="secondary"
                                        onClick={() => router.post(route('admin.payments.check', p.id), {}, { preserveScroll: true })}
                                    >
                                        Ask Paystack
                                    </Button>
                                ),
                        },
                    ]}
                />
                <Pagination page={payments} />
            </div>
        </AdminLayout>
    );
}
