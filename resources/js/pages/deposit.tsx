import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { DataTable } from '@/components/wf/data-table';
import { DepositStatus } from '@/components/wf/deposit-status';
import { FormField, selectClass } from '@/components/wf/form-field';
import { OfflineNotice, PaymentHistory, type PaymentRow } from '@/components/wf/payment-history';
import AppLayout from '@/layouts/app-layout';
import { formatKobo } from '@/lib/money';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Wallet', href: '/wallet' },
    { title: 'Deposit', href: '/deposit' },
];

type Method = { id: number; name: string; instructions: string; min: number; max: number };
type Manual = { id: number; method: string; amount: number; reference: string; status: string; reason: string | null; at: string };

/** A deposit by bank transfer: pay Wiifarm's account, then send the proof for an admin to check (section 6). */
function BankTransfer({ methods }: { methods: Method[] }) {
    const [formId, setFormId] = useState(() => crypto.randomUUID());
    const form = useForm<{ method_id: number; amount: string; reference: string; proof: File | null; form_id: string }>({
        method_id: methods[0]?.id ?? 0,
        amount: '',
        reference: '',
        proof: null,
        form_id: formId,
    });
    const method = methods.find((m) => m.id === form.data.method_id);
    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('deposit.manual'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                const next = crypto.randomUUID();
                setFormId(next);
                form.reset();
                form.setData('form_id', next);
            },
        });
    };

    return (
        <form onSubmit={submit} className="bg-card shadow-soft max-w-md space-y-4 rounded-xl border p-4">
            {methods.length > 1 && (
                <FormField id="m-method" label="Method" error={form.errors.method_id}>
                    <select
                        id="m-method"
                        className={selectClass}
                        value={form.data.method_id}
                        onChange={(e) => form.setData('method_id', Number(e.target.value))}
                    >
                        {methods.map((m) => (
                            <option key={m.id} value={m.id}>
                                {m.name}
                            </option>
                        ))}
                    </select>
                </FormField>
            )}
            {method && (
                <div className="bg-muted rounded-md p-3 text-sm whitespace-pre-line">
                    <p className="mb-1 font-medium">1. Pay to</p>
                    {method.instructions}
                </div>
            )}
            <p className="text-sm font-medium">2. Then tell us about your payment</p>
            <FormField
                id="m-amount"
                label="Amount you paid (₦)"
                error={form.errors.amount}
                hint={method ? `From ${formatKobo(method.min)} to ${formatKobo(method.max)}.` : undefined}
            >
                <Input id="m-amount" inputMode="decimal" value={form.data.amount} onChange={(e) => form.setData('amount', e.target.value)} />
            </FormField>
            <FormField id="m-ref" label="Payment reference or narration" error={form.errors.reference}>
                <Input id="m-ref" value={form.data.reference} onChange={(e) => form.setData('reference', e.target.value)} />
            </FormField>
            <FormField id="m-proof" label="Receipt or screenshot" error={form.errors.proof} hint="A photo or PDF, up to 5 MB. Only admins see it.">
                <Input
                    id="m-proof"
                    type="file"
                    accept="image/jpeg,image/png,image/webp,application/pdf"
                    onChange={(e) => form.setData('proof', e.target.files?.[0] ?? null)}
                />
            </FormField>
            <Button disabled={form.processing || !form.data.amount.trim() || !form.data.reference.trim() || !form.data.proof}>
                Send for checking
            </Button>
        </form>
    );
}

export default function Deposit({
    online,
    min,
    max,
    payments,
    methods,
    manual,
}: {
    online: boolean;
    min: number;
    max: number;
    payments: PaymentRow[];
    methods: Method[];
    manual: Manual[];
}) {
    const form = useForm({ amount: '' });
    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('deposit.pay'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Deposit" />
            <div className="max-w-3xl space-y-6 p-4">
                <p className="text-sm">
                    Add money to your main balance with Paystack (card, bank transfer or USSD). It is credited as soon as Paystack confirms the
                    payment.
                </p>
                {!online && <OfflineNotice />}
                <form onSubmit={submit} className="bg-card shadow-soft max-w-md space-y-4 rounded-xl border p-4">
                    <div className="grid gap-2">
                        <Label htmlFor="d-amount">Amount (₦)</Label>
                        <Input
                            id="d-amount"
                            inputMode="decimal"
                            placeholder="e.g. 20,000"
                            value={form.data.amount}
                            onChange={(e) => form.setData('amount', e.target.value)}
                        />
                        <p className="text-muted-foreground text-xs">
                            From {formatKobo(min)} to {formatKobo(max)}.
                        </p>
                        <InputError message={form.errors.amount} />
                    </div>
                    <Button disabled={!online || !form.data.amount.trim() || form.processing}>Pay with Paystack</Button>
                </form>
                <section className="space-y-2">
                    <h2 className="font-medium">Your deposits</h2>
                    <PaymentHistory rows={payments} />
                </section>

                {methods.length > 0 && (
                    <section className="space-y-3">
                        <h2 className="font-medium">Or pay by bank transfer</h2>
                        <p className="text-muted-foreground text-sm">
                            Pay into Wiifarm's account, then send us the proof. The money is added to your main balance once an admin has checked that
                            it arrived, usually within a working day.
                        </p>
                        <BankTransfer methods={methods} />
                    </section>
                )}
                {manual.length > 0 && (
                    <section className="space-y-2">
                        <h2 className="font-medium">Your bank-transfer deposits</h2>
                        <DataTable
                            rows={manual}
                            rowKey={(d) => d.id}
                            columns={[
                                { key: 'at', header: 'Sent', cell: (d) => d.at, className: 'whitespace-nowrap' },
                                { key: 'ref', header: 'Reference', cell: (d) => d.reference },
                                {
                                    key: 'status',
                                    header: 'Status',
                                    cell: (d) => (
                                        <>
                                            <DepositStatus status={d.status} />
                                            {d.reason && <span className="text-muted-foreground block text-xs">{d.reason}</span>}
                                        </>
                                    ),
                                },
                                { key: 'amount', header: 'Amount', align: 'right', cell: (d) => formatKobo(d.amount) },
                            ]}
                        />
                    </section>
                )}
            </div>
        </AppLayout>
    );
}
