import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { DataTable } from '@/components/wf/data-table';
import { orderKindName } from '@/components/wf/order-badges';
import AdminLayout, { useAdminCan } from '@/layouts/admin-layout';
import { formatKobo } from '@/lib/money';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { StoreStatusBadge } from './index';

interface StoreDetail {
    id: number;
    name: string;
    kind: 'mega' | 'sub';
    label: string;
    status: string;
    state: string;
    address: string;
    phone: string;
    owner: { id: number; username: string; name: string };
    sponsor: string;
    mega: string | null;
    subs: { id: number; name: string; status: string }[];
    deposit: number;
    deposit_reference: string | null;
    activated_at: string | null;
}

interface Props {
    store: StoreDetail;
    wallet: number;
    stock: { id: number; name: string; units: number }[];
    handovers: { id: number; number: string; kind: string; units: number; at: string }[];
}

function Activate({ store }: { store: StoreDetail }) {
    const form = useForm({ reference: '' });
    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('admin.stockists.activate', store.id), { preserveScroll: true });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">Activate the store</CardTitle>
                <CardDescription>
                    Once the {formatKobo(store.deposit)} deposit is received. Activation pays the stockist sponsor bonus to 10 generations and adds
                    the store PV, once only.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form onSubmit={submit} className="grid gap-2">
                    <Label htmlFor="reference">Deposit payment reference</Label>
                    <Input
                        id="reference"
                        value={form.data.reference}
                        onChange={(e) => form.setData('reference', e.target.value)}
                        placeholder="e.g. the bank transfer reference"
                    />
                    <InputError message={form.errors.reference} />
                    <Button className="w-fit" disabled={form.processing || !form.data.reference.trim()}>
                        Deposit received: activate
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}

function Standing({ store }: { store: StoreDetail }) {
    const active = store.status === 'active';
    const form = useForm({ active: !active, reason: '' });
    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('admin.stockists.standing', store.id), {
            preserveScroll: true,
            onSuccess: () => form.setData({ active: active, reason: '' }),
        });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">{active ? 'Deactivate the store' : 'Reactivate the store'}</CardTitle>
                <CardDescription>A deactivated store cannot hand over orders.</CardDescription>
            </CardHeader>
            <CardContent>
                <form onSubmit={submit} className="grid gap-2">
                    <Label htmlFor="standing-reason">Reason</Label>
                    <Textarea id="standing-reason" rows={2} value={form.data.reason} onChange={(e) => form.setData('reason', e.target.value)} />
                    <InputError message={form.errors.reason} />
                    <Button className="w-fit" variant={active ? 'destructive' : 'secondary'} disabled={form.processing || !form.data.reason.trim()}>
                        {active ? 'Deactivate' : 'Reactivate'}
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}

function Allocate({ store, stock }: { store: StoreDetail; stock: Props['stock'] }) {
    const form = useForm({ product_id: stock[0]?.id ?? 0, units: '', form_id: crypto.randomUUID() });
    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('admin.stockists.allocate', store.id), {
            preserveScroll: true,
            onSuccess: () => form.setData({ ...form.data, units: '', form_id: crypto.randomUUID() }),
        });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">Allocate stock</CardTitle>
                <CardDescription>The manufacturing company sends stock to this Mega store. Its Sub stores collect from it.</CardDescription>
            </CardHeader>
            <CardContent>
                <form onSubmit={submit} className="grid gap-3 sm:grid-cols-2">
                    <div className="grid gap-1.5">
                        <Label htmlFor="alloc-product">Product</Label>
                        <select
                            id="alloc-product"
                            className="border-input bg-background h-10 rounded-md border px-3 text-sm"
                            value={form.data.product_id}
                            onChange={(e) => form.setData('product_id', Number(e.target.value))}
                        >
                            {stock.map((p) => (
                                <option key={p.id} value={p.id}>
                                    {p.name}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="alloc-units">Units</Label>
                        <Input
                            id="alloc-units"
                            inputMode="numeric"
                            value={form.data.units}
                            onChange={(e) => form.setData('units', e.target.value.replace(/\D/g, ''))}
                        />
                    </div>
                    <div className="sm:col-span-2">
                        <InputError message={form.errors.units} />
                        <Button className="mt-2" variant="secondary" disabled={form.processing || !form.data.units}>
                            Allocate
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}

export default function StockistShow({ store, wallet, stock, handovers }: Props) {
    const can = useAdminCan();
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/admin' },
        { title: 'Stockists', href: '/admin/stockists' },
        { title: store.name, href: `/admin/stockists/${store.id}` },
    ];

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={store.name} />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-xl font-semibold">{store.name}</h1>
                            <StoreStatusBadge status={store.status} />
                        </div>
                        <p className="text-muted-foreground text-sm">
                            {store.label}
                            {store.mega && `, collects from ${store.mega}`} · {store.state} · {store.address} · {store.phone}
                        </p>
                        <p className="text-muted-foreground text-sm">
                            Stockist{' '}
                            {can('members.view') ? (
                                <Link href={route('admin.members.show', store.owner.id)} className="underline underline-offset-4">
                                    @{store.owner.username}
                                </Link>
                            ) : (
                                `@${store.owner.username}`
                            )}{' '}
                            · sponsor @{store.sponsor}
                            {store.activated_at && ` · active since ${store.activated_at} (deposit ref. ${store.deposit_reference})`}
                        </p>
                    </div>
                    <div className="text-right">
                        <p className="text-muted-foreground text-sm">Stockist wallet</p>
                        <p className="text-2xl font-semibold tabular-nums">{formatKobo(wallet)}</p>
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Stock</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            {stock.map((p) => (
                                <div key={p.id} className="flex justify-between">
                                    <span>{p.name}</span>
                                    <span className="font-medium tabular-nums">{p.units.toLocaleString('en-NG')} units</span>
                                </div>
                            ))}
                            {store.subs.length > 0 && (
                                <p className="text-muted-foreground pt-2">Sub stores: {store.subs.map((s) => s.name).join(', ')}</p>
                            )}
                        </CardContent>
                    </Card>
                    <div className="space-y-6">
                        {store.status === 'pending' && can('stockists.manage') && <Activate store={store} />}
                        {store.status !== 'pending' && can('stockists.manage') && <Standing store={store} />}
                        {store.kind === 'mega' && store.status === 'active' && can('stock.allocate') && <Allocate store={store} stock={stock} />}
                    </div>
                </div>

                <section className="space-y-2">
                    <h2 className="font-medium">Handovers</h2>
                    <DataTable
                        rows={handovers}
                        rowKey={(h) => h.id}
                        emptyTitle="No handovers yet"
                        columns={[
                            { key: 'at', header: 'When', cell: (h) => h.at, className: 'whitespace-nowrap' },
                            {
                                key: 'number',
                                header: 'Order',
                                cell: (h) =>
                                    can('orders.view') ? (
                                        <Link href={route('admin.orders.show', h.id)} className="underline underline-offset-4">
                                            {h.number}
                                        </Link>
                                    ) : (
                                        h.number
                                    ),
                            },
                            { key: 'kind', header: 'Kind', cell: (h) => orderKindName(h.kind) },
                            { key: 'units', header: 'Units', align: 'right', cell: (h) => h.units },
                        ]}
                    />
                </section>
            </div>
        </AdminLayout>
    );
}
