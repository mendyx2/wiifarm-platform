import InputError from '@/components/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { CategoryFilter, type Category } from '@/components/wf/category-filter';
import { ProductImage } from '@/components/wf/product-image';
import AppLayout from '@/layouts/app-layout';
import { formatKobo } from '@/lib/money';
import { pv } from '@/lib/network';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { Clock } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

type Product = { id: number; name: string; description: string | null; price: number; pv: number; image: string | null; category_id: number | null };

interface ShopProps {
    products: Product[];
    categories: Category[];
    wallets: { main: number; product: number };
    canBuy: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Shop', href: '/shop' }];

export default function Shop({ products, categories, wallets, canBuy }: ShopProps) {
    const [category, setCategory] = useState<number | null>(null);
    const shown = category === null ? products : products.filter((p) => p.category_id === category);
    const form = useForm<{ quantities: Record<number, number>; wallet: 'main' | 'product'; form_id: string }>({
        quantities: Object.fromEntries(products.map((p) => [p.id, 0])),
        wallet: wallets.product > 0 ? 'product' : 'main',
        form_id: crypto.randomUUID(),
    });
    const errors = form.errors as Record<string, string>;

    const total = products.reduce((sum, p) => sum + p.price * (form.data.quantities[p.id] || 0), 0);
    const totalPv = products.reduce((sum, p) => sum + p.pv * (form.data.quantities[p.id] || 0), 0);
    const units = products.reduce((sum, p) => sum + (form.data.quantities[p.id] || 0), 0);
    const enough = wallets[form.data.wallet] >= total;

    const setQty = (id: number, value: string) => {
        const n = Math.max(0, Math.min(10000, parseInt(value.replace(/\D/g, '') || '0', 10)));
        form.setData('quantities', { ...form.data.quantities, [id]: n });
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('shop.store'), { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Shop" />
            <form onSubmit={submit} className="space-y-6 p-4">
                {!canBuy && (
                    <Alert variant="info">
                        <Clock className="size-4" />
                        <AlertDescription>You can buy products once your package is paid.</AlertDescription>
                    </Alert>
                )}
                <p className="text-muted-foreground max-w-2xl text-sm">
                    Pay from your wallet, then collect your order from any stockist with the pickup code you receive. The code never expires, and the
                    whole order is handed over at once.
                </p>

                <CategoryFilter categories={categories} used={products.map((p) => p.category_id)} value={category} onChange={setCategory} />
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {shown.map((p) => (
                        <Card key={p.id} className="overflow-hidden py-0">
                            <ProductImage src={p.image} name={p.name} className="rounded-none border-b" />
                            <CardContent className="grid gap-3 p-5">
                                <div>
                                    <h2 className="text-base font-semibold">{p.name}</h2>
                                    {p.description && <p className="text-muted-foreground text-sm">{p.description}</p>}
                                </div>
                                <p className="text-sm">
                                    <span className="text-lg font-semibold tabular-nums">{formatKobo(p.price)}</span> a unit · {pv(p.pv)} PV
                                </p>
                                <div className="grid gap-1.5">
                                    <Label htmlFor={`qty-${p.id}`}>Units</Label>
                                    <Input
                                        id={`qty-${p.id}`}
                                        inputMode="numeric"
                                        className="w-28"
                                        disabled={!canBuy}
                                        value={form.data.quantities[p.id] || ''}
                                        placeholder="0"
                                        onChange={(e) => setQty(p.id, e.target.value)}
                                    />
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <Card className="py-0">
                    <CardContent className="flex flex-wrap items-end justify-between gap-4 p-5">
                        <div className="grid gap-2">
                            <span className="text-sm font-medium">Pay from</span>
                            <div className="flex flex-wrap gap-2">
                                {(['product', 'main'] as const).map((w) => (
                                    <button
                                        type="button"
                                        key={w}
                                        disabled={!canBuy}
                                        onClick={() => form.setData('wallet', w)}
                                        aria-pressed={form.data.wallet === w}
                                        className={cn(
                                            'rounded-md border px-3 py-2 text-left text-sm',
                                            form.data.wallet === w ? 'border-primary ring-primary/30 ring-2' : 'hover:bg-muted',
                                        )}
                                    >
                                        <span className="block font-medium">{w === 'main' ? 'Main balance' : 'Product wallet'}</span>
                                        <span className="text-muted-foreground tabular-nums">{formatKobo(wallets[w])}</span>
                                    </button>
                                ))}
                            </div>
                            <InputError message={errors.wallet} />
                            <InputError message={errors.quantities} />
                        </div>
                        <div className="grid gap-2 text-right">
                            <p className="text-sm">
                                {units} unit{units === 1 ? '' : 's'} · {pv(totalPv)} PV
                            </p>
                            <p className="text-2xl font-semibold tabular-nums">{formatKobo(total)}</p>
                            {total > 0 && !enough && <p className="text-destructive text-sm">Not enough in this wallet.</p>}
                            <Button disabled={!canBuy || form.processing || total === 0 || !enough}>Pay {formatKobo(total)}</Button>
                        </div>
                    </CardContent>
                </Card>
            </form>
        </AppLayout>
    );
}
