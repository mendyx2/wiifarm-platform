import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { DataTable } from '@/components/wf/data-table';
import { FormField, selectClass } from '@/components/wf/form-field';
import { Pagination, type Paginated } from '@/components/wf/pagination';
import { ReportTabs } from '@/components/wf/report-tabs';
import AdminLayout from '@/layouts/admin-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

type Login = { id: number; guard: string; name: string; ip: string | null; agent: string | null; at: string };

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin' },
    { title: 'Reports', href: '/admin/reports/financial' },
    { title: 'Login history', href: '/admin/reports/logins' },
];

const kinds: Record<string, string> = { web: 'Member', admin: 'Admin', affiliate: 'Affiliate' };

export default function Logins({ filters, logins }: { filters: { who: string; guard: string }; logins: Paginated<Login> }) {
    const [f, setF] = useState(filters);

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Login history" />
            <div className="space-y-4 p-4">
                <ReportTabs current="admin.reports.logins" />
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        router.get(route('admin.reports.logins'), Object.fromEntries(Object.entries(f).filter(([, v]) => v)));
                    }}
                    className="flex flex-wrap items-end gap-3"
                >
                    <FormField id="l-guard" label="Who">
                        <select id="l-guard" className={selectClass} value={f.guard} onChange={(e) => setF({ ...f, guard: e.target.value })}>
                            <option value="">Everyone</option>
                            <option value="web">Members</option>
                            <option value="admin">Admins</option>
                            <option value="affiliate">Affiliates</option>
                        </select>
                    </FormField>
                    <FormField id="l-who" label="Username">
                        <Input id="l-who" className="w-44" value={f.who} onChange={(e) => setF({ ...f, who: e.target.value })} />
                    </FormField>
                    <Button variant="secondary">Filter</Button>
                </form>
                <DataTable
                    rows={logins.data}
                    rowKey={(l) => l.id}
                    emptyTitle="No sign-ins yet"
                    columns={[
                        { key: 'at', header: 'When', cell: (l) => l.at, className: 'whitespace-nowrap' },
                        {
                            key: 'who',
                            header: 'Who',
                            cell: (l) => (
                                <>
                                    {l.name}
                                    <span className="text-muted-foreground block text-xs">{kinds[l.guard] ?? l.guard}</span>
                                </>
                            ),
                        },
                        { key: 'ip', header: 'Internet address', cell: (l) => l.ip ?? '—' },
                        {
                            key: 'agent',
                            header: 'Device',
                            cell: (l) => <span className="text-muted-foreground line-clamp-2 text-xs">{l.agent ?? '—'}</span>,
                        },
                    ]}
                />
                <Pagination page={logins} />
            </div>
        </AdminLayout>
    );
}
