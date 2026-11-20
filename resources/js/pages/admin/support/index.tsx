import { DataTable } from '@/components/wf/data-table';
import { Pagination, type Paginated } from '@/components/wf/pagination';
import { TicketStatus } from '@/components/wf/support-thread';
import AdminLayout from '@/layouts/admin-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

type Ticket = { id: number; subject: string; status: string; opened: string; last: string | null; member: string };

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin' },
    { title: 'Support', href: '/admin/support' },
];

const tabs: [string, string][] = [
    ['open', 'Needs a reply'],
    ['answered', 'Waiting for the member'],
    ['closed', 'Closed'],
    ['all', 'All'],
];

export default function AdminSupport({ tickets, status }: { tickets: Paginated<Ticket>; status: string }) {
    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Support" />
            <div className="space-y-4 p-4">
                <nav className="flex flex-wrap gap-2" aria-label="Filter by status">
                    {tabs.map(([value, label]) => (
                        <Link
                            key={value}
                            href={route('admin.support.index', value === 'open' ? {} : { status: value })}
                            className={cn(
                                'rounded-full border px-3 py-1 text-sm',
                                status === value ? 'bg-primary text-primary-foreground border-primary' : 'hover:bg-muted',
                            )}
                        >
                            {label}
                        </Link>
                    ))}
                </nav>
                <DataTable
                    rows={tickets.data}
                    rowKey={(t) => t.id}
                    emptyTitle={status === 'open' ? 'Nothing waiting' : 'No tickets here'}
                    columns={[
                        {
                            key: 'subject',
                            header: 'Ticket',
                            cell: (t) => (
                                <Link href={route('admin.support.show', t.id)} className="group block">
                                    <span className="font-medium group-hover:underline">
                                        #{t.id} {t.subject}
                                    </span>
                                    <span className="text-muted-foreground block text-xs">@{t.member}</span>
                                </Link>
                            ),
                        },
                        { key: 'status', header: 'Status', cell: (t) => <TicketStatus status={t.status} admin /> },
                        { key: 'last', header: 'Last message', cell: (t) => t.last, className: 'whitespace-nowrap' },
                    ]}
                />
                <Pagination page={tickets} />
            </div>
        </AdminLayout>
    );
}
