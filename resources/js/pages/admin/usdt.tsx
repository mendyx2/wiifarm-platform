import InputError from '@/components/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { DataTable } from '@/components/wf/data-table';
import { FormField } from '@/components/wf/form-field';
import { Pagination, type Paginated } from '@/components/wf/pagination';
import { UsdtStatus } from '@/components/wf/usdt-deposit';
import AdminLayout, { useAdminCan } from '@/layouts/admin-layout';
import { formatKobo } from '@/lib/money';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

type Settings = { enabled: boolean; address: string; naira_per_usdt: string; ready: boolean; network: string; api_key: boolean };
type Row = {
    id: number;
    member: string;
    amount: number;
    usdt: string;
    received: string | null;
    suggested: string | null;
    status: string;
    txid: string | null;
    sender: string | null;
    sent_at: string | null;
    at: string;
    expires_at: string;
    note: string | null;
    credited: number | null;
};
type Unmatched = { txid: string; usdt: string; sender: string | null; sent_at: string | null };

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin' },
    { title: 'Deposits', href: '/admin/deposits' },
    { title: 'USDT', href: '/admin/usdt' },
];

const tabs: [string, string][] = [
    ['review', 'To check'],
    ['paid', 'Added'],
    ['waiting', 'Waiting'],
    ['expired', 'Time ran out'],
    ['rejected', 'Refused'],
    ['all', 'All'],
];

function SettingsCard({ settings }: { settings: Settings }) {
    const form = useForm({ address: settings.address, naira_per_usdt: settings.naira_per_usdt, enabled: settings.enabled });
    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.put(route('admin.usdt.settings'), { preserveScroll: true });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">Settings</CardTitle>
                <CardDescription>
                    Network: {settings.network}. TronGrid key: {settings.api_key ? 'set' : 'not set (add TRONGRID_API_KEY to the server settings)'}.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form onSubmit={submit} className="grid gap-3 sm:grid-cols-3">
                    <div className="sm:col-span-2">
                        <FormField id="u-address" label="Wiifarm's USDT (TRC20) address" error={form.errors.address}>
                            <Input
                                id="u-address"
                                className="font-mono"
                                value={form.data.address}
                                onChange={(e) => form.setData('address', e.target.value)}
                            />
                        </FormField>
                    </div>
                    <FormField id="u-rate" label="Naira for 1 USDT" error={form.errors.naira_per_usdt}>
                        <Input
                            id="u-rate"
                            inputMode="decimal"
                            value={form.data.naira_per_usdt}
                            onChange={(e) => form.setData('naira_per_usdt', e.target.value)}
                        />
                    </FormField>
                    <div className="flex items-center gap-2 sm:col-span-3">
                        <Checkbox id="u-on" checked={form.data.enabled} onCheckedChange={(v) => form.setData('enabled', v === true)} />
                        <Label htmlFor="u-on">Members can deposit USDT</Label>
                    </div>
                    <InputError message={form.errors.enabled} />
                    <Button className="w-fit sm:col-start-1" disabled={form.processing || !form.isDirty}>
                        Save
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}

function Decide({ row }: { row: Row }) {
    const approve = useForm({ amount: row.suggested ?? '' });
    const reject = useForm({ reason: '' });

    return (
        <div className="grid gap-2 sm:grid-cols-2">
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    approve.post(route('admin.usdt.approve', row.id), { preserveScroll: true });
                }}
                className="flex flex-wrap items-end gap-2"
            >
                <FormField id={`a-${row.id}`} label="Add to the balance (₦)" error={approve.errors.amount}>
                    <Input
                        id={`a-${row.id}`}
                        className="w-36"
                        value={approve.data.amount}
                        onChange={(e) => approve.setData('amount', e.target.value)}
                    />
                </FormField>
                <Button size="sm" disabled={approve.processing}>
                    Approve
                </Button>
            </form>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    reject.post(route('admin.usdt.reject', row.id), { preserveScroll: true });
                }}
                className="flex flex-wrap items-end gap-2"
            >
                <FormField id={`r-${row.id}`} label="Or refuse, with a reason" error={reject.errors.reason}>
                    <Input id={`r-${row.id}`} value={reject.data.reason} onChange={(e) => reject.setData('reason', e.target.value)} />
                </FormField>
                <Button size="sm" variant="destructive" disabled={reject.processing || !reject.data.reason.trim()}>
                    Refuse
                </Button>
            </form>
        </div>
    );
}

export default function AdminUsdt({
    status,
    settings,
    explorer,
    deposits,
    unmatched,
}: {
    status: string;
    settings: Settings;
    explorer: string;
    deposits: Paginated<Row>;
    unmatched: Unmatched[];
}) {
    const can = useAdminCan();
    const tx = (id: string | null) =>
        id ? (
            <a href={explorer + id} target="_blank" rel="noreferrer" className="font-mono text-xs underline underline-offset-4">
                {id.slice(0, 10)}…
            </a>
        ) : (
            '—'
        );

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="USDT deposits" />
            <div className="space-y-6 p-4">
                <p className="text-muted-foreground max-w-3xl text-sm">
                    Members send USDT (TRC20) to Wiifarm's address. Each deposit asks for an exact amount no other open deposit asks for, so a payment
                    is matched and added by itself. A payment with the wrong amount or after its hour comes here to be checked.
                </p>
                {!settings.ready && (
                    <Alert variant="warning">
                        <AlertDescription>USDT deposits are switched off. Set the address and the rate, then switch them on.</AlertDescription>
                    </Alert>
                )}
                {can('deposits.methods') && <SettingsCard settings={settings} />}

                <section className="space-y-3">
                    <nav className="flex flex-wrap gap-2" aria-label="Filter by status">
                        {tabs.map(([value, label]) => (
                            <Link
                                key={value}
                                href={route('admin.usdt.index', value === 'review' ? {} : { status: value })}
                                preserveScroll
                                className={cn(
                                    'rounded-full border px-3 py-1 text-sm',
                                    status === value ? 'bg-primary text-primary-foreground border-primary' : 'hover:bg-muted',
                                )}
                            >
                                {label}
                            </Link>
                        ))}
                    </nav>
                    {status === 'review' && deposits.data.length > 0 && (
                        <Alert variant="warning">
                            <AlertDescription>
                                Anyone can see payments to a public address. Before approving, confirm with the member that the payment is theirs.
                            </AlertDescription>
                        </Alert>
                    )}
                    <DataTable
                        rows={deposits.data}
                        rowKey={(d) => d.id}
                        emptyTitle={status === 'review' ? 'Nothing to check' : 'No USDT deposits here'}
                        columns={[
                            {
                                key: 'who',
                                header: 'Deposit',
                                cell: (d) => (
                                    <>
                                        #{d.id} · @{d.member}
                                        <span className="text-muted-foreground block text-xs">{d.at}</span>
                                    </>
                                ),
                            },
                            {
                                key: 'usdt',
                                header: 'Asked / received',
                                cell: (d) => (
                                    <>
                                        {d.usdt} USDT
                                        {d.received && d.received !== d.usdt && (
                                            <span className="text-warning block text-xs">received {d.received}</span>
                                        )}
                                    </>
                                ),
                            },
                            { key: 'tx', header: 'Transaction', cell: (d) => tx(d.txid) },
                            {
                                key: 'status',
                                header: 'Status',
                                cell: (d) => (
                                    <>
                                        <UsdtStatus status={d.status} />
                                        {d.note && <span className="text-muted-foreground block text-xs">{d.note}</span>}
                                    </>
                                ),
                            },
                            { key: 'amount', header: 'Naira', align: 'right', cell: (d) => formatKobo(d.credited ?? d.amount) },
                        ]}
                    />
                    {status === 'review' &&
                        can('deposits.decide') &&
                        deposits.data.map((d) => (
                            <Card key={d.id}>
                                <CardHeader className="pb-2">
                                    <CardTitle className="text-sm">
                                        #{d.id} · @{d.member}: asked {d.usdt} USDT for {formatKobo(d.amount)}, received {d.received} USDT
                                        {d.sent_at && ` on ${d.sent_at}`}
                                    </CardTitle>
                                    <CardDescription>
                                        From {d.sender ?? 'unknown'} · {tx(d.txid)} · its hour ended {d.expires_at}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <Decide row={d} />
                                </CardContent>
                            </Card>
                        ))}
                    <Pagination page={deposits} />
                </section>

                <section className="space-y-2">
                    <h2 className="font-medium">Payments that fit no deposit</h2>
                    <p className="text-muted-foreground text-sm">
                        Found on the address but not claimed. The member who sent one can still claim it with its transaction ID.
                    </p>
                    <DataTable
                        rows={unmatched}
                        rowKey={(t) => t.txid}
                        emptyTitle="None"
                        columns={[
                            { key: 'tx', header: 'Transaction', cell: (t) => tx(t.txid) },
                            { key: 'from', header: 'From', cell: (t) => <span className="font-mono text-xs">{t.sender ?? '—'}</span> },
                            { key: 'at', header: 'Sent', cell: (t) => t.sent_at ?? '—', className: 'whitespace-nowrap' },
                            { key: 'usdt', header: 'USDT', align: 'right', cell: (t) => t.usdt },
                        ]}
                    />
                </section>
            </div>
        </AdminLayout>
    );
}
