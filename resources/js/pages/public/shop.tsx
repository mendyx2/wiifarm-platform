import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { FormField } from '@/components/wf/form-field';
import { OfflineNotice } from '@/components/wf/payment-history';
import PublicLayout from '@/layouts/public-layout';
import { formatKobo } from '@/lib/money';
import { Head, useForm } from '@inertiajs/react';
import { Minus, Plus } from 'lucide-react';
import { FormEventHandler } from 'react';

type Product = { id: number; name: string; description: string | null; price: number };

export default function PublicShop({ products, referredBy, online }: { products: Product[]; referredBy: string | null; online: boolean }) {
    const form = useForm({ name: '', phone: '', email: '', quantities: {} as Record<number, number> });
    const qty = (id: number) => form.data.quantities[id] ?? 0;
    const setQty = (id: number, n: number) => form.setData('quantities', { ...form.data.quantities, [id]: Math.max(0, Math.min(500, n)) });
    const total = products.reduce((sum, p) => sum + p.price * qty(p.id), 0);
    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('public.order.store'));
    };

    return (
        <PublicLayout>
            <Head title="Shop" />
            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-semibold">Wiifarm products</h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Pay online, then collect your order from any Wiifarm stockist with the pickup code you receive.
                        {referredBy && ` Recommended by ${referredBy}.`}
                    </p>
                </div>
                {!online && <OfflineNotice />}
                <form onSubmit={submit} className="grid gap-6 lg:grid-cols-[2fr_1fr]">
                    <section className="grid content-start gap-3 sm:grid-cols-2" aria-label="Products">
                        {products.map((p) => (
                            <div key={p.id} className="bg-card flex flex-col justify-between gap-3 rounded-xl border p-4">
                                <div>
                                    <h2 className="font-medium">{p.name}</h2>
                                    {p.description && <p className="text-muted-foreground mt-1 text-sm">{p.description}</p>}
                                    <p className="mt-2 text-lg font-semibold">{formatKobo(p.price)}</p>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Button
                                        type="button"
                                        size="icon"
                                        variant="secondary"
                                        aria-label={`One less ${p.name}`}
                                        onClick={() => setQty(p.id, qty(p.id) - 1)}
                                    >
                                        <Minus className="size-4" />
                                    </Button>
                                    <Input
                                        aria-label={`How many ${p.name}`}
                                        inputMode="numeric"
                                        className="w-16 text-center"
                                        value={qty(p.id)}
                                        onChange={(e) => setQty(p.id, Number(e.target.value.replace(/\D/g, '')) || 0)}
                                    />
                                    <Button
                                        type="button"
                                        size="icon"
                                        variant="secondary"
                                        aria-label={`One more ${p.name}`}
                                        onClick={() => setQty(p.id, qty(p.id) + 1)}
                                    >
                                        <Plus className="size-4" />
                                    </Button>
                                </div>
                            </div>
                        ))}
                        {products.length === 0 && <p className="text-muted-foreground text-sm">No products on sale right now.</p>}
                    </section>

                    <section className="bg-card h-fit space-y-4 rounded-xl border p-4" aria-label="Your details">
                        <h2 className="font-medium">Your details</h2>
                        <FormField id="b-name" label="Full name" error={form.errors.name}>
                            <Input id="b-name" autoComplete="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                        </FormField>
                        <FormField id="b-phone" label="Phone number" error={form.errors.phone} hint="The stockist may call you.">
                            <Input
                                id="b-phone"
                                inputMode="tel"
                                autoComplete="tel"
                                value={form.data.phone}
                                onChange={(e) => form.setData('phone', e.target.value)}
                            />
                        </FormField>
                        <FormField id="b-email" label="Email address" error={form.errors.email} hint="Paystack sends your receipt here.">
                            <Input
                                id="b-email"
                                type="email"
                                autoComplete="email"
                                value={form.data.email}
                                onChange={(e) => form.setData('email', e.target.value)}
                            />
                        </FormField>
                        <div className="flex items-center justify-between border-t pt-3">
                            <span className="text-sm">Total</span>
                            <span className="text-lg font-semibold">{formatKobo(total)}</span>
                        </div>
                        <InputError message={form.errors.quantities} />
                        <Button className="w-full" disabled={!online || total === 0 || form.processing}>
                            Pay {formatKobo(total)} with Paystack
                        </Button>
                    </section>
                </form>
            </div>
        </PublicLayout>
    );
}
