import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { DataTable } from '@/components/wf/data-table';
import { Pagination, type Paginated } from '@/components/wf/pagination';
import AdminLayout from '@/layouts/admin-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { MailX } from 'lucide-react';

type Event = { key: string; name: string; who: string; enabled: boolean; edited: boolean; sent30: number };
type LogRow = { id: number; event: string; email: string; subject: string; status: string; created_at: string; username: string | null };

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin' },
    { title: 'Messages', href: '/admin/messages' },
];

const statusBadge: Record<string, 'success' | 'warning' | 'danger'> = { sent: 'success', queued: 'warning', failed: 'danger' };

export default function Messages({
    emailOn,
    events,
    filter,
    log,
}: {
    emailOn: boolean;
    events: Event[];
    filter: string | null;
    log: Paginated<LogRow>;
}) {
    const names = Object.fromEntries(events.map((e) => [e.key, e.name]));

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Messages" />
            <div className="space-y-4 p-4">
                <p className="text-muted-foreground max-w-3xl text-sm">
                    The emails members receive when something happens to them. Click a message to change its text or switch it off. Every message sent
                    is listed below.
                </p>
                {!emailOn && (
                    <Alert variant="warning">
                        <MailX className="size-4" />
                        <AlertDescription>
                            Email to members is switched off for the whole site, so no message is sent. It is switched on under{' '}
                            <Link href={route('admin.settings.edit')} className="underline">
                                Settings
                            </Link>
                            .
                        </AlertDescription>
                    </Alert>
                )}

                <ul className="bg-card shadow-soft divide-y rounded-xl border">
                    {events.map((e) => (
                        <li key={e.key}>
                            <Link
                                href={route('admin.messages.edit', { event: e.key })}
                                className="hover:bg-muted flex flex-wrap items-center justify-between gap-3 px-4 py-3"
                            >
                                <div className="min-w-0">
                                    <p className="font-medium">{e.name}</p>
                                    <p className="text-muted-foreground text-xs">To: {e.who}</p>
                                </div>
                                <div className="flex shrink-0 items-center gap-2 text-xs">
                                    <span className="text-muted-foreground">{e.sent30} in 30 days</span>
                                    {e.edited && <Badge variant="info">Changed</Badge>}
                                    <Badge variant={e.enabled ? 'success' : 'secondary'}>{e.enabled ? 'On' : 'Off'}</Badge>
                                </div>
                            </Link>
                        </li>
                    ))}
                </ul>

                <section className="space-y-2">
                    <div className="flex flex-wrap items-center gap-2">
                        <h2 className="font-medium">Sent</h2>
                        <Link
                            href={route('admin.messages.index')}
                            preserveScroll
                            className={cn('rounded-md px-2 py-1 text-xs', !filter ? 'bg-accent text-accent-foreground' : 'hover:bg-muted')}
                        >
                            All
                        </Link>
                        {events.map((e) => (
                            <Link
                                key={e.key}
                                href={route('admin.messages.index', { event: e.key })}
                                preserveScroll
                                className={cn(
                                    'rounded-md px-2 py-1 text-xs',
                                    filter === e.key ? 'bg-accent text-accent-foreground' : 'hover:bg-muted',
                                )}
                            >
                                {e.name}
                            </Link>
                        ))}
                    </div>
                    <DataTable
                        rows={log.data}
                        rowKey={(r) => r.id}
                        emptyTitle="No messages sent yet"
                        columns={[
                            { key: 'at', header: 'Date', cell: (r) => r.created_at, className: 'whitespace-nowrap' },
                            {
                                key: 'to',
                                header: 'To',
                                cell: (r) => (
                                    <>
                                        {r.username ? `@${r.username}` : 'Not a member'}
                                        <span className="text-muted-foreground block text-xs">{r.email}</span>
                                    </>
                                ),
                            },
                            {
                                key: 'subject',
                                header: 'Message',
                                cell: (r) => (
                                    <>
                                        {r.subject}
                                        <span className="text-muted-foreground block text-xs">{names[r.event] ?? r.event}</span>
                                    </>
                                ),
                            },
                            {
                                key: 'status',
                                header: 'Status',
                                cell: (r) => (
                                    <Badge variant={statusBadge[r.status] ?? 'secondary'}>
                                        {r.status === 'queued' ? 'Waiting' : r.status === 'sent' ? 'Sent' : 'Failed'}
                                    </Badge>
                                ),
                            },
                        ]}
                    />
                    <Pagination page={log} />
                </section>
            </div>
        </AdminLayout>
    );
}
