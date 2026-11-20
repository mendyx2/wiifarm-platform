import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Pagination, type Paginated } from '@/components/wf/pagination';
import { StatementTable } from '@/components/wf/statement-table';
import AppLayout from '@/layouts/app-layout';
import { coreWallets, type StatementRow } from '@/lib/ledger';
import { formatKobo } from '@/lib/money';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

interface WalletProps {
    balances: Record<string, number>;
    labels: Record<string, string>;
    wallet: string;
    entries: Paginated<StatementRow>;
    transfers: Transfers | null;
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Wallet', href: '/wallet' }];

function WalletTile({
    kind,
    label,
    balance,
    active,
    large = false,
}: {
    kind: string;
    label: string;
    balance: number;
    active: boolean;
    large?: boolean;
}) {
    return (
        <Link href={route('wallet', kind === 'main' ? {} : { wallet: kind })} preserveScroll aria-current={active ? 'true' : undefined}>
            <Card className={cn('hover:border-primary h-full py-0 transition-colors', active && 'border-primary ring-primary/30 ring-2')}>
                <CardContent className={cn('p-4', large && 'p-5')}>
                    <p className="text-muted-foreground text-sm">{label}</p>
                    <p className={cn('mt-1 font-semibold tabular-nums', large ? 'text-3xl' : 'text-lg')}>{formatKobo(balance)}</p>
                </CardContent>
            </Card>
        </Link>
    );
}

type Transfers = { min: number; max: number; fixed: number; percent: string };

/** Sends money from the main balance to another member's (section 6). */
function SendToMember({ rules, main }: { rules: Transfers; main: number }) {
    const form = useForm({ username: '', amount: '', password: '', form_id: crypto.randomUUID() });
    const errors = form.errors as Record<string, string>;
    const [who, setWho] = useState<{ name?: string; error?: string } | null>(null);
    const amount = Number(form.data.amount.replace(/[,\s₦]/g, '')) * 100 || 0;
    const charge = Math.min(amount, rules.fixed + Math.floor((amount * Math.round(Number(rules.percent) * 100)) / 10_000));

    const check = async () => {
        setWho(null);
        if (!form.data.username.trim()) return;
        const res = await fetch(route('wallet.recipient', { username: form.data.username.trim() }), { headers: { Accept: 'application/json' } });
        const body = await res.json().catch(() => ({}));
        setWho(res.ok ? { name: body.name } : { error: body.error ?? 'There is no member with this username.' });
    };
    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('wallet.send'), {
            preserveScroll: true,
            onSuccess: () => {
                form.setData({ username: '', amount: '', password: '', form_id: crypto.randomUUID() });
                setWho(null);
            },
            onFinish: () => form.setData('password', ''),
        });
    };

    return (
        <Card className="py-0">
            <CardContent className="p-5">
                <form onSubmit={submit} className="grid gap-3 sm:grid-cols-3">
                    <p className="font-medium sm:col-span-3">Send money to another member</p>
                    <div className="grid gap-1.5">
                        <Label htmlFor="st-user">Their username</Label>
                        <Input
                            id="st-user"
                            value={form.data.username}
                            onChange={(e) => {
                                form.setData('username', e.target.value);
                                setWho(null);
                            }}
                            onBlur={check}
                        />
                        {who?.name && <p className="text-success text-xs">{who.name}</p>}
                        {who?.error && <p className="text-destructive text-xs">{who.error}</p>}
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="st-amount">Amount (₦)</Label>
                        <Input id="st-amount" inputMode="decimal" value={form.data.amount} onChange={(e) => form.setData('amount', e.target.value)} />
                        <p className="text-muted-foreground text-xs">
                            {amount > 0
                                ? `They receive ${formatKobo(Math.max(0, amount - charge))}${charge > 0 ? ` (charge ${formatKobo(charge)})` : ''}.`
                                : `${formatKobo(rules.min)} to ${formatKobo(rules.max)}. You have ${formatKobo(main)}.`}
                        </p>
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="st-password">Your password, to confirm</Label>
                        <Input
                            id="st-password"
                            type="password"
                            autoComplete="current-password"
                            value={form.data.password}
                            onChange={(e) => form.setData('password', e.target.value)}
                        />
                        <InputError message={errors.password} />
                    </div>
                    <div className="flex flex-wrap items-center gap-3 sm:col-span-3">
                        <Button disabled={form.processing || !who?.name || !form.data.amount || !form.data.password}>Send</Button>
                        <InputError message={errors.send ?? errors.amount ?? errors.username} />
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}

function BonusTransfer({ balances, labels }: { balances: Record<string, number>; labels: Record<string, string> }) {
    const bonuses = Object.keys(labels).filter((k) => k.startsWith('bonus.') && balances[k] > 0);
    const form = useForm({ wallet: bonuses[0] ?? '', amount: '', form_id: crypto.randomUUID() });
    const errors = form.errors as Record<string, string>;
    if (bonuses.length === 0) return null;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('wallet.transfer'), {
            preserveScroll: true,
            onSuccess: () => form.setData({ ...form.data, amount: '', form_id: crypto.randomUUID() }),
        });
    };

    return (
        <Card className="py-0">
            <CardContent className="p-5">
                <form onSubmit={submit} className="flex flex-wrap items-end gap-3">
                    <div className="grid gap-1.5">
                        <Label htmlFor="bt-wallet">Move a bonus to your main balance</Label>
                        <select
                            id="bt-wallet"
                            className="border-input bg-background h-10 rounded-md border px-3 text-sm"
                            value={form.data.wallet}
                            onChange={(e) => form.setData('wallet', e.target.value)}
                        >
                            {bonuses.map((k) => (
                                <option key={k} value={k}>
                                    {labels[k]}: {formatKobo(balances[k])}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="bt-amount">Amount (₦)</Label>
                        <Input
                            id="bt-amount"
                            inputMode="decimal"
                            className="w-40"
                            value={form.data.amount}
                            onChange={(e) => form.setData('amount', e.target.value)}
                        />
                    </div>
                    <Button type="button" variant="ghost" onClick={() => form.setData('amount', (balances[form.data.wallet] / 100).toFixed(2))}>
                        All of it
                    </Button>
                    <Button disabled={form.processing || !form.data.amount}>Move</Button>
                    <div className="w-full">
                        <InputError message={errors.transfer} />
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}

export default function Wallet({ balances, labels, wallet, entries, transfers }: WalletProps) {
    const bonus = Object.keys(labels).filter((k) => k.startsWith('bonus.') && (balances[k] !== 0 || k === wallet));

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Wallet" />
            <div className="space-y-6 p-4">
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-[2fr_1fr_1fr_1fr_1fr]">
                    {coreWallets.map((k) => (
                        <WalletTile key={k} kind={k} label={labels[k]} balance={balances[k]} active={wallet === k} large={k === 'main'} />
                    ))}
                </div>

                <div className="flex justify-end gap-2">
                    <Button asChild>
                        <Link href={route('deposit')}>Deposit</Link>
                    </Button>
                    <Button asChild variant="secondary">
                        <Link href={route('withdraw')}>Withdraw</Link>
                    </Button>
                </div>

                <BonusTransfer balances={balances} labels={labels} />
                {transfers && <SendToMember rules={transfers} main={balances.main} />}

                <section className="space-y-2">
                    <h2 className="font-medium">Bonus wallets</h2>
                    {bonus.length === 0 ? (
                        <p className="text-muted-foreground text-sm">Your bonuses will appear here as you earn them.</p>
                    ) : (
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            {bonus.map((k) => (
                                <WalletTile key={k} kind={k} label={labels[k]} balance={balances[k]} active={wallet === k} />
                            ))}
                        </div>
                    )}
                </section>

                <section className="space-y-2">
                    <h2 className="font-medium">{labels[wallet]}: statement</h2>
                    <StatementTable rows={entries.data} />
                    <Pagination page={entries} />
                </section>
            </div>
        </AppLayout>
    );
}
