import InputError from '@/components/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatKobo } from '@/lib/money';
import { useForm } from '@inertiajs/react';
import { ShieldAlert } from 'lucide-react';
import { FormEventHandler } from 'react';
import { DataTable } from './data-table';

export type Method = {
    id: number;
    name: string;
    min: number;
    max: number;
    fixed_charge: number;
    percent_charge: string;
    fields: { key: string; label: string }[];
};
export type WalletOption = { key: string; label: string; balance: number };
export type WithdrawalRow = {
    id: number;
    method: string;
    amount: number;
    charge: number;
    payable: number;
    status: string;
    reason: string | null;
    reference: string | null;
    requested_at: string;
    decided_at: string | null;
};

/** "1,500.50" → kobo, or null; the same rule the server applies. */
function toKobo(text: string): number | null {
    const t = text.replace(/[,\s₦]/g, '');
    if (!/^\d{1,12}(\.\d{1,2})?$/.test(t)) return null;
    const [n, d = ''] = t.split('.');
    return parseInt(n, 10) * 100 + parseInt((d + '00').slice(0, 2), 10);
}

export function WithdrawalStatus({ status }: { status: string }) {
    if (status === 'paid') return <Badge variant="success">Paid</Badge>;
    if (status === 'rejected') return <Badge variant="danger">Refused</Badge>;
    return <Badge variant="warning">Waiting for an admin</Badge>;
}

/** Request a withdrawal: wallet, method, amount and the method's details, with the charge shown before sending. */
export function WithdrawForm({
    wallets,
    methods,
    action,
    blocked,
}: {
    wallets: WalletOption[];
    methods: Method[];
    action: string;
    blocked?: string | null;
}) {
    const form = useForm<{ wallet: string; method_id: number; amount: string; details: Record<string, string>; form_id: string }>({
        wallet: wallets[0]?.key ?? 'main',
        method_id: methods[0]?.id ?? 0,
        amount: '',
        details: {},
        form_id: crypto.randomUUID(),
    });
    const method = methods.find((m) => m.id === form.data.method_id);
    const wallet = wallets.find((w) => w.key === form.data.wallet);
    const kobo = toKobo(form.data.amount);
    const charge = method && kobo ? method.fixed_charge + Math.floor((kobo * Math.round(parseFloat(method.percent_charge) * 100)) / 10000) : 0;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(action, { preserveScroll: true, onSuccess: () => form.setData({ ...form.data, amount: '', form_id: crypto.randomUUID() }) });
    };

    if (methods.length === 0) {
        return <p className="text-muted-foreground text-sm">No way of being paid is set up yet.</p>;
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">Withdraw</CardTitle>
                <CardDescription>
                    The amount leaves your wallet at once. An admin sends the money and marks it paid; if they refuse it, it all comes back.
                </CardDescription>
            </CardHeader>
            <CardContent>
                {blocked ? (
                    <Alert variant="warning">
                        <ShieldAlert className="size-4" />
                        <AlertDescription>{blocked}</AlertDescription>
                    </Alert>
                ) : (
                    <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
                        {wallets.length > 1 && (
                            <div className="grid gap-1.5 sm:col-span-2">
                                <Label htmlFor="w-wallet">From</Label>
                                <select
                                    id="w-wallet"
                                    className="border-input bg-background h-10 rounded-md border px-3 text-sm"
                                    value={form.data.wallet}
                                    onChange={(e) => form.setData('wallet', e.target.value)}
                                >
                                    {wallets.map((w) => (
                                        <option key={w.key} value={w.key}>
                                            {w.label}: {formatKobo(w.balance)}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        )}
                        <div className="grid gap-1.5">
                            <Label htmlFor="w-method">Paid by</Label>
                            <select
                                id="w-method"
                                className="border-input bg-background h-10 rounded-md border px-3 text-sm"
                                value={form.data.method_id}
                                onChange={(e) => form.setData('method_id', Number(e.target.value))}
                            >
                                {methods.map((m) => (
                                    <option key={m.id} value={m.id}>
                                        {m.name}
                                    </option>
                                ))}
                            </select>
                            {method && (
                                <p className="text-muted-foreground text-xs">
                                    {formatKobo(method.min)} to {formatKobo(method.max)}
                                    {(method.fixed_charge > 0 || parseFloat(method.percent_charge) > 0) &&
                                        ` · charge ${formatKobo(method.fixed_charge)} + ${parseFloat(method.percent_charge)}%`}
                                </p>
                            )}
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="w-amount">Amount (₦)</Label>
                            <Input
                                id="w-amount"
                                inputMode="decimal"
                                value={form.data.amount}
                                onChange={(e) => form.setData('amount', e.target.value)}
                                placeholder={wallet ? `up to ${formatKobo(wallet.balance)}` : ''}
                            />
                        </div>
                        {method?.fields.map((f) => (
                            <div key={f.key} className="grid gap-1.5">
                                <Label htmlFor={`w-${f.key}`}>{f.label}</Label>
                                <Input
                                    id={`w-${f.key}`}
                                    value={form.data.details[f.key] ?? ''}
                                    onChange={(e) => form.setData('details', { ...form.data.details, [f.key]: e.target.value })}
                                />
                            </div>
                        ))}
                        <div className="grid gap-2 sm:col-span-2">
                            {kobo !== null && kobo > 0 && (
                                <p className="text-sm">
                                    You receive <b>{formatKobo(Math.max(0, kobo - charge))}</b>
                                    {charge > 0 && ` (${formatKobo(kobo)} less ${formatKobo(charge)} charge)`}
                                </p>
                            )}
                            <InputError message={form.errors.amount} />
                            <Button className="w-fit" disabled={form.processing || !kobo}>
                                Request the withdrawal
                            </Button>
                        </div>
                    </form>
                )}
            </CardContent>
        </Card>
    );
}

export function WithdrawalHistory({ rows }: { rows: WithdrawalRow[] }) {
    return (
        <DataTable
            rows={rows}
            rowKey={(w) => w.id}
            emptyTitle="No withdrawals yet"
            columns={[
                { key: 'at', header: 'Requested', cell: (w) => w.requested_at, className: 'whitespace-nowrap' },
                { key: 'method', header: 'Paid by', cell: (w) => w.method },
                {
                    key: 'status',
                    header: 'Status',
                    cell: (w) => (
                        <>
                            <WithdrawalStatus status={w.status} />
                            {w.reason && <span className="text-muted-foreground block text-xs">{w.reason}</span>}
                        </>
                    ),
                },
                { key: 'amount', header: 'Amount', align: 'right', cell: (w) => formatKobo(w.amount) },
                { key: 'payable', header: 'You receive', align: 'right', cell: (w) => formatKobo(w.payable) },
            ]}
        />
    );
}
