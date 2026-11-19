import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { FormField } from '@/components/wf/form-field';
import { OfflineNotice, PaymentHistory, type PaymentRow } from '@/components/wf/payment-history';
import AppLayout from '@/layouts/app-layout';
import { formatKobo } from '@/lib/money';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

type PackageOption = { id: number; name: string; price: number; pv: number; packs: number; per_match: number; cap: number; to_pay: number };
type Current = { id: number; name: string; pv: number; upgradable: boolean } | null;

const breadcrumbs: BreadcrumbItem[] = [{ title: 'My package', href: '/package' }];

export default function PackagePage({
    current,
    packages,
    online,
    payments,
}: {
    current: Current;
    packages: PackageOption[];
    online: boolean;
    payments: PaymentRow[];
}) {
    const choices = current ? packages.filter((p) => p.pv > current.pv) : packages;
    const form = useForm({ package_id: choices[0]?.id ?? 0 });
    const chosen = choices.find((p) => p.id === form.data.package_id);
    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('package.pay'));
    };
    const pin = useForm({ code: '', package_id: 0 });
    const redeem: FormEventHandler = (e) => {
        e.preventDefault();
        pin.transform((d) => ({ ...d, package_id: form.data.package_id }));
        pin.post(route('package.epin'), { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="My package" />
            <div className="max-w-5xl space-y-6 p-4">
                {current ? (
                    <p className="text-sm">
                        You are on <span className="font-medium">{current.name}</span> ({current.pv} PV).{' '}
                        {current.upgradable && choices.length > 0
                            ? 'To upgrade, pay the difference in price. The extra PV climbs your tree and the higher package’s extra packs are yours to collect.'
                            : 'There is no higher package to move to.'}
                    </p>
                ) : (
                    <p className="text-sm">
                        Choose your package. You become a member, with your place in the tree, as soon as the payment is confirmed. Your product packs
                        are collected from any stockist with the pickup code you will receive.
                    </p>
                )}

                {!online && <OfflineNotice />}

                {(!current || current.upgradable) && choices.length > 0 && (
                    <form onSubmit={submit} className="space-y-4">
                        <fieldset className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <legend className="sr-only">Package</legend>
                            {choices.map((p) => (
                                <label
                                    key={p.id}
                                    className={cn(
                                        'bg-card cursor-pointer rounded-xl border p-4 transition',
                                        form.data.package_id === p.id ? 'border-primary ring-primary/30 ring-2' : 'hover:border-primary/50',
                                    )}
                                >
                                    <input
                                        type="radio"
                                        name="package_id"
                                        value={p.id}
                                        checked={form.data.package_id === p.id}
                                        onChange={() => form.setData('package_id', p.id)}
                                        className="sr-only"
                                    />
                                    <span className="flex items-center justify-between">
                                        <span className="font-medium">{p.name}</span>
                                        <Badge variant="secondary">{p.pv} PV</Badge>
                                    </span>
                                    <span className="mt-2 block text-2xl font-semibold">{formatKobo(p.to_pay)}</span>
                                    {current && (
                                        <span className="text-muted-foreground block text-xs">
                                            {formatKobo(p.price)} minus the {current.name} price
                                        </span>
                                    )}
                                    <span className="text-muted-foreground mt-2 block text-xs">
                                        {p.packs} product {p.packs === 1 ? 'pack' : 'packs'} · {formatKobo(p.per_match)} a match, up to {p.cap} a day
                                    </span>
                                </label>
                            ))}
                        </fieldset>
                        <InputError message={form.errors.package_id} />
                        <Button disabled={!online || !chosen || form.processing}>
                            {chosen ? `Pay ${formatKobo(chosen.to_pay)} with Paystack` : 'Choose a package'}
                        </Button>
                        <p className="text-muted-foreground text-xs">You will pay on Paystack’s secure page and come back here.</p>
                    </form>
                )}

                {!current && chosen && (
                    <form onSubmit={redeem} className="bg-card shadow-soft max-w-xl space-y-3 rounded-xl border p-4">
                        <h2 className="font-medium">Or pay with an e-pin</h2>
                        <FormField
                            id="pin-code"
                            label={`E-pin for ${chosen.name}`}
                            error={pin.errors.code}
                            hint="The code a member gave you, such as K7QM-3XPA-R9TD."
                        >
                            <Input
                                id="pin-code"
                                autoComplete="off"
                                className="font-mono uppercase"
                                value={pin.data.code}
                                onChange={(e) => pin.setData('code', e.target.value)}
                            />
                        </FormField>
                        <Button variant="secondary" disabled={!pin.data.code.trim() || pin.processing}>
                            Pay {chosen.name} with this e-pin
                        </Button>
                    </form>
                )}

                <section className="space-y-2">
                    <h2 className="font-medium">Your package payments</h2>
                    <PaymentHistory rows={payments} />
                </section>
            </div>
        </AppLayout>
    );
}
