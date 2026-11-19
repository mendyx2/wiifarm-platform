import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { DataTable } from '@/components/wf/data-table';
import { EPinStatus, PinCode } from '@/components/wf/epin-status';
import { FormField, selectClass } from '@/components/wf/form-field';
import { Pagination, type Paginated } from '@/components/wf/pagination';
import AppLayout from '@/layouts/app-layout';
import { formatKobo } from '@/lib/money';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

type Pin = {
    id: number;
    code: string | null;
    amount: number;
    package: string | null;
    status: string;
    used_by: string | null;
    used_at: string | null;
    created_at: string;
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'E-pins', href: '/epins' }];

export default function EPins({
    visa,
    canBuy,
    max,
    packages,
    pins,
}: {
    visa: number;
    canBuy: boolean;
    max: number;
    packages: { id: number; name: string; price: number; pv: number }[];
    pins: Paginated<Pin>;
}) {
    const [formId, setFormId] = useState(() => crypto.randomUUID());
    const form = useForm({ package_id: packages[0]?.id ?? 0, quantity: 1, form_id: formId });
    const chosen = packages.find((p) => p.id === form.data.package_id);
    const total = (chosen?.price ?? 0) * (form.data.quantity || 0);
    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('epins.buy'), {
            preserveScroll: true,
            onSuccess: () => {
                const next = crypto.randomUUID();
                setFormId(next);
                form.setData('form_id', next);
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="E-pins" />
            <div className="max-w-5xl space-y-6 p-4">
                <p className="text-sm">
                    An e-pin pays one person’s package, once. Buy pins from your VISA wallet ({formatKobo(visa)} available) and give each code to the
                    person it is for: they enter it on their “My package” page after signing up.
                </p>
                {canBuy ? (
                    <form onSubmit={submit} className="bg-card shadow-soft grid max-w-xl gap-4 rounded-xl border p-4 sm:grid-cols-[2fr_1fr]">
                        <FormField id="p-package" label="Package" error={form.errors.package_id}>
                            <select
                                id="p-package"
                                className={selectClass}
                                value={form.data.package_id}
                                onChange={(e) => form.setData('package_id', Number(e.target.value))}
                            >
                                {packages.map((p) => (
                                    <option key={p.id} value={p.id}>
                                        {p.name}: {formatKobo(p.price)}
                                    </option>
                                ))}
                            </select>
                        </FormField>
                        <FormField id="p-quantity" label="How many" error={undefined}>
                            <Input
                                id="p-quantity"
                                type="number"
                                min={1}
                                max={max}
                                value={form.data.quantity}
                                onChange={(e) => form.setData('quantity', Number(e.target.value))}
                            />
                        </FormField>
                        <div className="sm:col-span-2">
                            <InputError message={form.errors.quantity} />
                            <Button disabled={form.processing || !chosen || total <= 0 || total > visa} className="mt-1">
                                Buy for {formatKobo(total)} from my VISA wallet
                            </Button>
                            {total > visa && <p className="text-destructive mt-2 text-sm">Your VISA wallet does not have enough for this.</p>}
                        </div>
                    </form>
                ) : (
                    <p className="text-muted-foreground text-sm">You can buy e-pins once your own package is paid.</p>
                )}

                <section className="space-y-2">
                    <h2 className="font-medium">Your e-pins</h2>
                    <DataTable
                        rows={pins.data}
                        rowKey={(p) => p.id}
                        emptyTitle="No e-pins yet"
                        columns={[
                            {
                                key: 'code',
                                header: 'Code',
                                cell: (p) => (p.code ? <PinCode code={p.code} /> : <span className="text-muted-foreground">—</span>),
                            },
                            { key: 'package', header: 'For', cell: (p) => p.package ?? '—' },
                            { key: 'amount', header: 'Worth', align: 'right', cell: (p) => formatKobo(p.amount), className: 'whitespace-nowrap' },
                            { key: 'status', header: 'Status', cell: (p) => <EPinStatus status={p.status} /> },
                            {
                                key: 'used',
                                header: 'Used by',
                                cell: (p) => (p.used_by ? `@${p.used_by} on ${p.used_at}` : '—'),
                            },
                            { key: 'created_at', header: 'Bought', cell: (p) => p.created_at, className: 'whitespace-nowrap' },
                        ]}
                    />
                    <Pagination page={pins} />
                </section>
            </div>
        </AppLayout>
    );
}
