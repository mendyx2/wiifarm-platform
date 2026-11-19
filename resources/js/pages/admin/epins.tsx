import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { DataTable } from '@/components/wf/data-table';
import { EPinStatus, PinCode } from '@/components/wf/epin-status';
import { FormField, selectClass } from '@/components/wf/form-field';
import { Pagination, type Paginated } from '@/components/wf/pagination';
import AdminLayout, { useAdminCan } from '@/layouts/admin-layout';
import { formatKobo } from '@/lib/money';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

type Row = {
    id: number;
    code: string | null;
    amount: number;
    package: string | null;
    status: string;
    created_by: string;
    reason: string | null;
    used_by: string | null;
    used_at: string | null;
    cancel_reason: string | null;
    created_at: string;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin' },
    { title: 'E-pins', href: '/admin/epins' },
];

const tabs: [string, string][] = [
    ['all', 'All'],
    ['active', 'Unused'],
    ['used', 'Used'],
    ['cancelled', 'Cancelled'],
];

function CreatePins({ packages }: { packages: { id: number; name: string; price: number }[] }) {
    const [formId, setFormId] = useState(() => crypto.randomUUID());
    const form = useForm({ package_id: packages[0]?.id ?? 0, quantity: 1, reason: '', form_id: formId });
    const chosen = packages.find((p) => p.id === form.data.package_id);
    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('admin.epins.store'), {
            onSuccess: () => {
                const next = crypto.randomUUID();
                setFormId(next);
                form.setData((d) => ({ ...d, reason: '', form_id: next }));
            },
        });
    };

    return (
        <form onSubmit={submit} className="bg-card shadow-soft grid gap-4 rounded-xl border p-4 md:grid-cols-[1fr_8rem_2fr_auto] md:items-end">
            <FormField id="a-package" label="Package" error={form.errors.package_id}>
                <select
                    id="a-package"
                    className={selectClass}
                    value={form.data.package_id}
                    onChange={(e) => form.setData('package_id', Number(e.target.value))}
                >
                    {packages.map((p) => (
                        <option key={p.id} value={p.id}>
                            {p.name}: {formatKobo(p.price)}
                        </option>
                    ))}
                </select>
            </FormField>
            <FormField id="a-quantity" label="How many" error={form.errors.quantity}>
                <Input
                    id="a-quantity"
                    type="number"
                    min={1}
                    max={50}
                    value={form.data.quantity}
                    onChange={(e) => form.setData('quantity', Number(e.target.value))}
                />
            </FormField>
            <FormField id="a-reason" label="Why (for the audit log)" error={form.errors.reason}>
                <Input
                    id="a-reason"
                    value={form.data.reason}
                    onChange={(e) => form.setData('reason', e.target.value)}
                    placeholder="e.g. Launch event prizes"
                />
            </FormField>
            <Button disabled={form.processing || !form.data.reason.trim() || !chosen}>
                Create {form.data.quantity || 0} for {formatKobo((chosen?.price ?? 0) * (form.data.quantity || 0))}
            </Button>
        </form>
    );
}

function Cancel({ row }: { row: Row }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ reason: '' });
    if (!open) {
        return (
            <Button size="sm" variant="ghost" className="text-destructive" onClick={() => setOpen(true)}>
                Cancel
            </Button>
        );
    }
    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                form.post(route('admin.epins.cancel', row.id), { preserveScroll: true, onSuccess: () => setOpen(false) });
            }}
            className="flex flex-wrap items-start gap-2"
        >
            <div>
                <Input
                    aria-label="Reason"
                    placeholder="Reason"
                    className="h-8 w-44"
                    value={form.data.reason}
                    onChange={(e) => form.setData('reason', e.target.value)}
                />
                <InputError message={form.errors.reason} />
            </div>
            <Button size="sm" variant="destructive" disabled={!form.data.reason.trim() || form.processing}>
                Cancel pin
            </Button>
        </form>
    );
}

export default function AdminEPins({
    pins,
    packages,
    status,
    q,
}: {
    pins: Paginated<Row>;
    packages: { id: number; name: string; price: number }[];
    status: string;
    q: string;
}) {
    const can = useAdminCan();
    const [search, setSearch] = useState(q);

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="E-pins" />
            <div className="space-y-4 p-4">
                <p className="text-muted-foreground max-w-3xl text-sm">
                    An e-pin pays one registration, once. Members buy them from their VISA wallet; admins can create them, with a reason. Cancelling
                    an unused pin bought by a member returns its value to their VISA wallet.
                </p>
                {can('epins.manage') && <CreatePins packages={packages} />}
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <nav className="flex flex-wrap gap-2" aria-label="Filter by status">
                        {tabs.map(([value, label]) => (
                            <Link
                                key={value}
                                href={route('admin.epins.index', value === 'all' ? {} : { status: value })}
                                className={cn(
                                    'rounded-full border px-3 py-1 text-sm',
                                    status === value ? 'bg-primary text-primary-foreground border-primary' : 'hover:bg-muted',
                                )}
                            >
                                {label}
                            </Link>
                        ))}
                    </nav>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            router.get(
                                route('admin.epins.index'),
                                { ...(status !== 'all' && { status }), ...(search && { q: search }) },
                                { preserveState: true },
                            );
                        }}
                        className="flex gap-2"
                    >
                        <Input
                            aria-label="Search e-pins"
                            placeholder="Buyer or user's username"
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
                    rows={pins.data}
                    rowKey={(p) => p.id}
                    emptyTitle="No e-pins"
                    columns={[
                        {
                            key: 'code',
                            header: 'Code',
                            cell: (p) => (p.code ? <PinCode code={p.code} /> : <span className="text-muted-foreground">#{p.id}</span>),
                        },
                        { key: 'package', header: 'For', cell: (p) => p.package ?? '—' },
                        { key: 'amount', header: 'Worth', align: 'right', cell: (p) => formatKobo(p.amount), className: 'whitespace-nowrap' },
                        {
                            key: 'created',
                            header: 'Created by',
                            cell: (p) => (
                                <>
                                    {p.created_by}
                                    <span className="text-muted-foreground block text-xs">{p.created_at}</span>
                                    {p.reason && <span className="text-muted-foreground block text-xs">{p.reason}</span>}
                                </>
                            ),
                        },
                        {
                            key: 'status',
                            header: 'Status',
                            cell: (p) => (
                                <>
                                    <EPinStatus status={p.status} />
                                    {p.used_by && (
                                        <span className="text-muted-foreground block text-xs">
                                            @{p.used_by}, {p.used_at}
                                        </span>
                                    )}
                                    {p.cancel_reason && <span className="text-muted-foreground block text-xs">{p.cancel_reason}</span>}
                                </>
                            ),
                        },
                        ...(can('epins.manage') ? [{ key: 'act', header: '', cell: (p: Row) => p.status === 'active' && <Cancel row={p} /> }] : []),
                    ]}
                />
                <Pagination page={pins} />
            </div>
        </AdminLayout>
    );
}
