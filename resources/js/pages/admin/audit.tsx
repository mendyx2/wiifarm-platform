import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { EmptyState } from '@/components/wf/empty-state';
import { Pagination, type Paginated } from '@/components/wf/pagination';
import AdminLayout, { useAdminCan } from '@/layouts/admin-layout';
import { auditActionName } from '@/lib/audit-actions';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

type Values = Record<string, unknown> | null;
type Entry = {
    id: number;
    at: string;
    admin: { id: number; username: string } | null;
    action: string;
    subject_type: string | null;
    subject_id: number | null;
    old_values: Values;
    new_values: Values;
    note: string | null;
    ip_address: string | null;
};
type Filters = { admin?: string; action?: string; subject_type?: string; subject_id?: string; from?: string; to?: string };

interface AuditProps {
    entries: Paginated<Entry>;
    filters: Filters;
    admins: { id: number; username: string }[];
    actions: string[];
    subjectTypes: string[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin' },
    { title: 'Audit log', href: '/admin/audit' },
];

/** Where a record's own admin page is, when it has one and this admin may open it. */
function subjectLink(type: string | null, id: number | null, can: (permission: string) => boolean): string | null {
    if (!type || !id) return null;
    if (type === 'User' && can('members.view')) return route('admin.members.show', id);
    if (type === 'KycSubmission' && can('kyc.view')) return route('admin.kyc.show', id);
    if (type === 'Admin' && can('admins.manage')) return route('admin.admins.edit', id);
    if (type === 'Role' && can('roles.manage')) return route('admin.roles.edit', id);
    return null;
}

const subjectNames: Record<string, string> = { User: 'Member', KycSubmission: 'KYC submission', Admin: 'Admin', Role: 'Role' };

/** Plain names for the fields that appear in changes. */
const fieldNames: Record<string, string> = {
    status: 'status',
    ban_reason: 'ban reason',
    withdrawals_blocked: 'withdrawals blocked',
    withdrawals_block_reason: 'block reason',
    sponsor_id: 'sponsor (member #)',
    first_name: 'first name',
    last_name: 'last name',
    dial_code: 'dialling code',
    is_active: 'active',
    balance: 'balance (kobo)',
    amount: 'amount (kobo)',
};

const show = (v: unknown) =>
    v === null || v === undefined || v === ''
        ? '—'
        : Array.isArray(v)
          ? v.join(', ') || '—'
          : typeof v === 'boolean'
            ? v
                ? 'yes'
                : 'no'
            : String(v);

/** Before and after, one line per field that changed. */
function Changes({ old, now }: { old: Values; now: Values }) {
    const keys = Array.from(new Set([...Object.keys(old ?? {}), ...Object.keys(now ?? {})]));
    if (keys.length === 0) return <span className="text-muted-foreground">—</span>;

    return (
        <dl className="space-y-1">
            {keys.map((k) => (
                <div key={k} className="text-xs">
                    <dt className="text-muted-foreground inline">{fieldNames[k] ?? k.replace(/[._]/g, ' ')}: </dt>
                    <dd className="inline">
                        {old && k in old && <span className="text-destructive line-through">{show(old[k])}</span>}
                        {old && k in old && now && k in now && ' → '}
                        {now && k in now && <span className="text-success font-medium">{show(now[k])}</span>}
                    </dd>
                </div>
            ))}
        </dl>
    );
}

export default function AuditLog({ entries, filters, admins, actions, subjectTypes }: AuditProps) {
    const [f, setF] = useState<Filters>(filters);
    const can = useAdminCan();

    const apply: FormEventHandler = (e) => {
        e.preventDefault();
        router.get(route('admin.audit'), Object.fromEntries(Object.entries(f).filter(([, v]) => v)), { preserveState: true });
    };

    const select = (key: keyof Filters, label: string, options: [string, string][]) => (
        <div className="grid gap-1">
            <Label htmlFor={`f-${key}`} className="text-xs">
                {label}
            </Label>
            <select
                id={`f-${key}`}
                className="border-input bg-background h-10 rounded-md border px-3 text-sm"
                value={f[key] ?? ''}
                onChange={(e) => setF({ ...f, [key]: e.target.value })}
            >
                <option value="">All</option>
                {options.map(([value, text]) => (
                    <option key={value} value={value}>
                        {text}
                    </option>
                ))}
            </select>
        </div>
    );

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Audit log" />
            <div className="space-y-4 p-4">
                <p className="text-muted-foreground text-sm">
                    Every admin action that changed data. Entries can only be added: nobody can change or delete them, from this panel or anywhere
                    else.
                </p>

                <form onSubmit={apply} className="flex flex-wrap items-end gap-3">
                    {select(
                        'admin',
                        'Admin',
                        admins.map((a) => [String(a.id), `@${a.username}`]),
                    )}
                    {select(
                        'action',
                        'Action',
                        actions.map((a) => [a, auditActionName(a)]),
                    )}
                    {select(
                        'subject_type',
                        'Record',
                        subjectTypes.map((t) => [t, subjectNames[t] ?? t]),
                    )}
                    <div className="grid gap-1">
                        <Label htmlFor="f-subject_id" className="text-xs">
                            Record number
                        </Label>
                        <Input
                            id="f-subject_id"
                            inputMode="numeric"
                            className="w-28"
                            value={f.subject_id ?? ''}
                            onChange={(e) => setF({ ...f, subject_id: e.target.value.replace(/\D/g, '') })}
                        />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="f-from" className="text-xs">
                            From
                        </Label>
                        <Input id="f-from" type="date" value={f.from ?? ''} onChange={(e) => setF({ ...f, from: e.target.value })} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="f-to" className="text-xs">
                            To
                        </Label>
                        <Input id="f-to" type="date" value={f.to ?? ''} onChange={(e) => setF({ ...f, to: e.target.value })} />
                    </div>
                    <Button type="submit" variant="secondary">
                        Show
                    </Button>
                    {Object.keys(filters).length > 0 && (
                        <Link href={route('admin.audit')} className="text-muted-foreground text-sm underline underline-offset-4">
                            Clear
                        </Link>
                    )}
                </form>

                {entries.data.length === 0 ? (
                    <EmptyState title="No entries" description="Nothing matches these filters." />
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/60 text-muted-foreground">
                                <tr>
                                    {['When', 'Admin', 'Action', 'Record', 'Change', 'Reason', 'IP address'].map((h) => (
                                        <th key={h} scope="col" className="px-4 py-3 text-left font-medium">
                                            {h}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {entries.data.map((e) => {
                                    const link = subjectLink(e.subject_type, e.subject_id, can);
                                    const label = e.subject_type ? `${subjectNames[e.subject_type] ?? e.subject_type} #${e.subject_id}` : '—';
                                    return (
                                        <tr key={e.id} className="border-t align-top">
                                            <td className="px-4 py-3 whitespace-nowrap">{e.at}</td>
                                            <td className="px-4 py-3">{e.admin ? `@${e.admin.username}` : 'System'}</td>
                                            <td className="px-4 py-3">{auditActionName(e.action)}</td>
                                            <td className="px-4 py-3 whitespace-nowrap">
                                                {link ? (
                                                    <Link href={link} className="underline underline-offset-4">
                                                        {label}
                                                    </Link>
                                                ) : (
                                                    label
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                <Changes old={e.old_values} now={e.new_values} />
                                            </td>
                                            <td className="px-4 py-3">{e.note ?? '—'}</td>
                                            <td className="text-muted-foreground px-4 py-3 text-xs">{e.ip_address ?? '—'}</td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}
                <Pagination page={entries} />
            </div>
        </AdminLayout>
    );
}
