import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { FormField } from '@/components/wf/form-field';
import { formatKobo } from '@/lib/money';
import { useForm } from '@inertiajs/react';
import { FormEventHandler, useEffect, useState } from 'react';

export type UsdtInfo = {
    open: { id: number; amount: number; usdt: string; address: string; expires_at: string } | null;
    rate: number;
    network: string;
};
export type UsdtRow = { id: number; amount: number; usdt: string; status: string; note: string | null; at: string };

const statusLabels: Record<string, [string, 'warning' | 'success' | 'danger' | 'secondary']> = {
    waiting: ['Waiting for payment', 'warning'],
    review: ['Being checked', 'warning'],
    paid: ['Added', 'success'],
    expired: ['Time ran out', 'secondary'],
    rejected: ['Not approved', 'danger'],
};

export function UsdtStatus({ status }: { status: string }) {
    const [label, variant] = statusLabels[status] ?? [status, 'secondary'];
    return <Badge variant={variant}>{label}</Badge>;
}

function Copy({ text }: { text: string }) {
    const [done, setDone] = useState(false);
    return (
        <button
            type="button"
            className="text-primary text-xs font-medium underline-offset-4 hover:underline"
            onClick={() => {
                navigator.clipboard?.writeText(text);
                setDone(true);
            }}
        >
            {done ? 'Copied' : 'Copy'}
        </button>
    );
}

function minutesLeft(iso: string) {
    return Math.max(0, Math.ceil((new Date(iso).getTime() - Date.now()) / 60_000));
}

/** Pay USDT (TRC20) to Wiifarm's address: the exact amount, within the hour (specification section 6). */
export function UsdtDeposit({ usdt }: { usdt: UsdtInfo }) {
    const start = useForm({ usdt_amount: '' });
    const claim = useForm({ txid: '' });
    const [left, setLeft] = useState(() => (usdt.open ? minutesLeft(usdt.open.expires_at) : 0));
    useEffect(() => {
        if (!usdt.open) return;
        const at = usdt.open.expires_at;
        setLeft(minutesLeft(at));
        const timer = setInterval(() => setLeft(minutesLeft(at)), 15_000);
        return () => clearInterval(timer);
    }, [usdt.open]);

    const doStart: FormEventHandler = (e) => {
        e.preventDefault();
        start.post(route('deposit.usdt'), { preserveScroll: true });
    };
    const doClaim: FormEventHandler = (e) => {
        e.preventDefault();
        claim.post(route('deposit.usdt.claim'), { preserveScroll: true, onSuccess: () => claim.reset() });
    };

    return (
        <div className="bg-card shadow-soft max-w-md space-y-4 rounded-xl border p-4">
            {usdt.open ? (
                <div className="space-y-3 text-sm">
                    <p>
                        Send <span className="font-semibold">exactly</span> this amount of USDT on the{' '}
                        <span className="font-semibold">Tron (TRC20)</span> network{usdt.network !== 'mainnet' && ` (test network: ${usdt.network})`}:
                    </p>
                    <div className="bg-muted flex items-center justify-between gap-2 rounded-md p-3">
                        <span className="text-2xl font-bold tabular-nums">{usdt.open.usdt} USDT</span>
                        <Copy text={usdt.open.usdt} />
                    </div>
                    <p className="text-muted-foreground">To Wiifarm's address:</p>
                    <div className="bg-muted flex items-center justify-between gap-2 rounded-md p-3">
                        <span className="font-mono text-xs break-all">{usdt.open.address}</span>
                        <Copy text={usdt.open.address} />
                    </div>
                    <p>
                        {formatKobo(usdt.open.amount)} is added to your main balance when it arrives.{' '}
                        {left > 0 ? `${left} minutes left.` : 'Time is up.'}
                    </p>
                    <p className="text-muted-foreground text-xs">
                        Send the exact amount, in one payment, or it has to be checked by hand. Other networks (ERC20, BEP20) or other coins are not
                        received.
                    </p>
                </div>
            ) : (
                <form onSubmit={doStart} className="space-y-3">
                    <FormField
                        id="u-amount"
                        label="Amount to add (₦)"
                        error={start.errors.usdt_amount}
                        hint={`₦${(usdt.rate / 100).toLocaleString('en-NG')} = 1 USDT. You will see the exact USDT to send.`}
                    >
                        <Input
                            id="u-amount"
                            inputMode="decimal"
                            value={start.data.usdt_amount}
                            onChange={(e) => start.setData('usdt_amount', e.target.value)}
                        />
                    </FormField>
                    <Button disabled={start.processing || !start.data.usdt_amount.trim()}>Pay with USDT</Button>
                </form>
            )}
            <form onSubmit={doClaim} className="space-y-2 border-t pt-4">
                <p className="text-sm font-medium">Already paid?</p>
                <p className="text-muted-foreground text-xs">
                    Payments are found by themselves within a minute or two. To check now, press the button, or paste your transaction ID (TXID).
                </p>
                <Input
                    aria-label="Transaction ID"
                    placeholder="Transaction ID (optional)"
                    value={claim.data.txid}
                    onChange={(e) => claim.setData('txid', e.target.value)}
                />
                <InputError message={claim.errors.txid} />
                <Button variant="secondary" disabled={claim.processing}>
                    Check my payment
                </Button>
            </form>
        </div>
    );
}
