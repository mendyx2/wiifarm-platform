import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { OfflineNotice, PaymentHistory, type PaymentRow } from '@/components/wf/payment-history';
import AppLayout from '@/layouts/app-layout';
import { formatKobo } from '@/lib/money';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Wallet', href: '/wallet' },
    { title: 'Deposit', href: '/deposit' },
];

export default function Deposit({ online, min, max, payments }: { online: boolean; min: number; max: number; payments: PaymentRow[] }) {
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
                <form onSubmit={submit} className="bg-card max-w-md space-y-4 rounded-xl border p-4">
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
            </div>
        </AppLayout>
    );
}
