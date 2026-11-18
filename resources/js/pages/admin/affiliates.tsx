import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { DataTable } from '@/components/wf/data-table';
import { Pagination, type Paginated } from '@/components/wf/pagination';
import AdminLayout, { useAdminCan } from '@/layouts/admin-layout';
import { formatKobo } from '@/lib/money';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

type Row = {
    id: number;
    code: string;
    name: string;
    email: string;
    phone: string;
    status: string;
    ban_reason: string | null;
    wallet: number;
    orders: number;
    joined: string;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin' },
    { title: 'Affiliates', href: '/admin/affiliates' },
];

function Standing({ row }: { row: Row }) {
    const banned = row.status === 'banned';
    const form = useForm({ ban: !banned, reason: '' });
    const [open, setOpen] = useState(false);
    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('admin.affiliates.standing', row.id), { preserveScroll: true, onSuccess: () => setOpen(false) });
    };

    if (!open) {
        return (
            <Button size="sm" variant={banned ? 'secondary' : 'ghost'} className={banned ? '' : 'text-destructive'} onClick={() => setOpen(true)}>
                {banned ? 'Unban' : 'Ban'}
            </Button>
        );
    }

    return (
        <form onSubmit={submit} className="flex flex-wrap items-start gap-2">
            <div>
                <Input
                    aria-label="Reason"
                    placeholder="Reason"
                    className="h-8 w-48"
                    value={form.data.reason}
                    onChange={(e) => form.setData('reason', e.target.value)}
                />
                <InputError message={form.errors.reason} />
            </div>
            <Button size="sm" variant={banned ? 'secondary' : 'destructive'} disabled={!form.data.reason.trim() || form.processing}>
                {banned ? 'Unban' : 'Ban'}
            </Button>
        </form>
    );
}

export default function Affiliates({ affiliates, q }: { affiliates: Paginated<Row>; q: string }) {
    const can = useAdminCan();
    const [search, setSearch] = useState(q);
    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        router.get(route('admin.affiliates.index'), search ? { q: search } : {}, { preserveState: true });
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Affiliates" />
            <div className="space-y-4 p-4">
                <form onSubmit={submit} className="flex max-w-xl gap-2">
                    <Input
                        aria-label="Search affiliates"
                        placeholder="Code, name or email"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                    />
                    <Button type="submit" variant="secondary">
                        <Search className="size-4" />
                        Search
                    </Button>
                </form>
                <DataTable
                    rows={affiliates.data}
                    rowKey={(a) => a.id}
                    emptyTitle="No affiliates yet"
                    columns={[
                        {
                            key: 'who',
                            header: 'Affiliate',
                            cell: (a) => (
                                <>
                                    <span className="font-medium">{a.name}</span>{' '}
                                    <span className="text-muted-foreground font-mono text-xs">{a.code}</span>
                                    <span className="text-muted-foreground block text-xs">
                                        {a.email} · {a.phone}
                                    </span>
                                    {a.status === 'banned' && (
                                        <Badge variant="danger" className="mt-1">
                                            Banned: {a.ban_reason}
                                        </Badge>
                                    )}
                                </>
                            ),
                        },
                        { key: 'orders', header: 'Sales', align: 'right', cell: (a) => a.orders },
                        { key: 'wallet', header: 'Wallet', align: 'right', cell: (a) => formatKobo(a.wallet) },
                        { key: 'joined', header: 'Joined', cell: (a) => a.joined, className: 'whitespace-nowrap' },
                        ...(can('affiliates.manage') ? [{ key: 'act', header: '', cell: (a: Row) => <Standing row={a} /> }] : []),
                    ]}
                />
                <Pagination page={affiliates} />
            </div>
        </AdminLayout>
    );
}
