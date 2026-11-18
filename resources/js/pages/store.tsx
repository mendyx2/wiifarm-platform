import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { DataTable } from '@/components/wf/data-table';
import { orderKindName } from '@/components/wf/order-badges';
import { type Paginated } from '@/components/wf/pagination';
import { StatementTable } from '@/components/wf/statement-table';
import AppLayout from '@/layouts/app-layout';
import { type StatementRow } from '@/lib/ledger';
import { formatKobo } from '@/lib/money';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { PackageCheck, Store as StoreIcon, TriangleAlert } from 'lucide-react';
import { FormEventHandler } from 'react';

interface StoreInfo {
    id: number;
    name: string;
    kind: 'mega' | 'sub';
    label: string;
    status: string;
    state: string;
    mega: string | null;
    subs: { id: number; name: string }[];
    rebate: number;
}

interface Preview {
    number: string;
    kind: string;
    units: number;
    buyer: string;
    items: { name: string; quantity: number }[];
    rebate: number;
}

interface StoreProps {
    store: StoreInfo | null;
    wallet?: number;
    statement?: Paginated<StatementRow>;
    stock?: { id: number; name: string; units: number }[];
    handovers?: { id: number; number: string; units: number; at: string }[];
    preview?: Preview | null;
    code?: string | null;
    lookupError?: string | null;
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'My store', href: '/store' }];

function HandOver({ preview, code, lookupError }: { preview?: Preview | null; code?: string | null; lookupError?: string | null }) {
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    const lookup = useForm({ code: code ?? '' });

    const find: FormEventHandler = (e) => {
        e.preventDefault();
        lookup.post(route('store.lookup'));
    };
    const confirm = () => router.post(route('store.handover'), { code }, { onSuccess: () => lookup.reset() });

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">Hand over an order</CardTitle>
                <CardDescription>
                    Ask the buyer for their pickup code. The whole order goes out at once, and your rebate is paid straight away.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                <form onSubmit={find} className="flex flex-wrap items-end gap-2">
                    <div className="grid gap-1.5">
                        <Label htmlFor="code">Pickup code</Label>
                        <Input
                            id="code"
                            autoComplete="off"
                            placeholder="XXXX-XXXX-XXXX"
                            className="w-64 font-mono tracking-widest uppercase"
                            value={lookup.data.code}
                            onChange={(e) => lookup.setData('code', e.target.value)}
                        />
                    </div>
                    <Button type="submit" variant="secondary" disabled={lookup.processing || lookup.data.code.trim().length < 12}>
                        Find the order
                    </Button>
                </form>
                <InputError message={lookupError ?? errors.code ?? lookup.errors.code} />

                {preview && (
                    <div className="space-y-3 rounded-lg border p-4">
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="font-medium">Order {preview.number}</span>
                            <Badge variant="secondary">{orderKindName(preview.kind)}</Badge>
                            <span className="text-muted-foreground text-sm">for {preview.buyer}</span>
                        </div>
                        <ul className="text-sm">
                            {preview.items.map((i) => (
                                <li key={i.name}>
                                    {i.quantity} × {i.name}
                                </li>
                            ))}
                        </ul>
                        <p className="text-sm">
                            Your rebate: <b>{formatKobo(preview.rebate)}</b>
                        </p>
                        <Button onClick={confirm}>
                            <PackageCheck className="size-4" />
                            Hand over {preview.units} units
                        </Button>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function PassToSub({ store, stock }: { store: StoreInfo; stock: { id: number; name: string; units: number }[] }) {
    const form = useForm({ sub_id: store.subs[0]?.id ?? 0, product_id: stock[0]?.id ?? 0, units: '', form_id: crypto.randomUUID() });
    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('store.collect'), {
            preserveScroll: true,
            onSuccess: () => form.setData({ ...form.data, units: '', form_id: crypto.randomUUID() }),
        });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">A Sub store collects stock</CardTitle>
                <CardDescription>Record what one of your Sub stores collects from you. It moves from your stock to theirs.</CardDescription>
            </CardHeader>
            <CardContent>
                <form onSubmit={submit} className="grid gap-3 sm:grid-cols-3">
                    <div className="grid gap-1.5">
                        <Label htmlFor="sub_id">Sub store</Label>
                        <select
                            id="sub_id"
                            className="border-input bg-background h-10 rounded-md border px-3 text-sm"
                            value={form.data.sub_id}
                            onChange={(e) => form.setData('sub_id', Number(e.target.value))}
                        >
                            {store.subs.map((s) => (
                                <option key={s.id} value={s.id}>
                                    {s.name}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="product_id">Product</Label>
                        <select
                            id="product_id"
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
                        <Label htmlFor="units">Units</Label>
                        <Input
                            id="units"
                            inputMode="numeric"
                            value={form.data.units}
                            onChange={(e) => form.setData('units', e.target.value.replace(/\D/g, ''))}
                        />
                    </div>
                    <div className="sm:col-span-3">
                        <InputError message={form.errors.units} />
                        <Button className="mt-2" variant="secondary" disabled={form.processing || !form.data.units}>
                            Record the collection
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}

export default function MyStore({ store, wallet = 0, statement, stock = [], handovers = [], preview, code, lookupError }: StoreProps) {
    if (!store) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="My store" />
                <div className="p-4">
                    <Alert variant="info">
                        <StoreIcon className="size-4" />
                        <AlertTitle>You do not run a store</AlertTitle>
                        <AlertDescription>Stockist stores are set up by Wiifarm. Contact support if you want to become a stockist.</AlertDescription>
                    </Alert>
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="My store" />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">{store.name}</h1>
                        <p className="text-muted-foreground text-sm">
                            {store.label} · {store.state}
                            {store.mega && ` · collects from ${store.mega}`} · {formatKobo(store.rebate)} a unit handed over
                        </p>
                    </div>
                    <div className="text-right">
                        <p className="text-muted-foreground text-sm">Stockist wallet</p>
                        <p className="text-2xl font-semibold tabular-nums">{formatKobo(wallet)}</p>
                    </div>
                </div>

                {store.status !== 'active' && (
                    <Alert variant="warning">
                        <TriangleAlert className="size-4" />
                        <AlertTitle>{store.status === 'pending' ? 'Waiting for activation' : 'This store is deactivated'}</AlertTitle>
                        <AlertDescription>The store can hand over orders once it is active.</AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-6 lg:grid-cols-[3fr_2fr]">
                    <HandOver preview={preview} code={code} lookupError={lookupError} />
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Stock</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            {stock.length === 0 ? (
                                <p className="text-muted-foreground">No stock yet.</p>
                            ) : (
                                stock.map((p) => (
                                    <div key={p.id} className="flex justify-between">
                                        <span>{p.name}</span>
                                        <span className="font-medium tabular-nums">{p.units.toLocaleString('en-NG')} units</span>
                                    </div>
                                ))
                            )}
                        </CardContent>
                    </Card>
                </div>

                {store.kind === 'mega' && store.subs.length > 0 && store.status === 'active' && <PassToSub store={store} stock={stock} />}

                <section className="space-y-2">
                    <h2 className="font-medium">Recent handovers</h2>
                    <DataTable
                        rows={handovers}
                        rowKey={(h) => h.id}
                        emptyTitle="No handovers yet"
                        columns={[
                            { key: 'at', header: 'When', cell: (h) => h.at, className: 'whitespace-nowrap' },
                            { key: 'number', header: 'Order', cell: (h) => h.number },
                            { key: 'units', header: 'Units', align: 'right', cell: (h) => h.units },
                        ]}
                    />
                </section>

                {statement && (
                    <section className="space-y-2">
                        <h2 className="font-medium">Stockist wallet: statement</h2>
                        <StatementTable rows={statement.data} />
                    </section>
                )}
            </div>
        </AppLayout>
    );
}
