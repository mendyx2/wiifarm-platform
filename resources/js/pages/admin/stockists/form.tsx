import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/admin-layout';
import { formatKobo } from '@/lib/money';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin' },
    { title: 'Stockists', href: '/admin/stockists' },
    { title: 'Set up a store', href: '/admin/stockists/create' },
];

export default function StoreForm({
    megas,
    kinds,
}: {
    megas: { id: number; name: string }[];
    kinds: { key: string; name: string; deposit: number }[];
}) {
    const form = useForm({ owner: '', sponsor: '', kind: 'sub', mega_store_id: megas[0]?.id ?? 0, name: '', state: '', address: '', phone: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('admin.stockists.store'));
    };

    const text = (key: 'owner' | 'sponsor' | 'name' | 'state' | 'address' | 'phone', label: string, hint?: string) => (
        <div className="grid gap-1.5">
            <Label htmlFor={key}>{label}</Label>
            <Input id={key} value={form.data[key]} onChange={(e) => form.setData(key, e.target.value)} />
            {hint && !form.errors[key] && <p className="text-muted-foreground text-xs">{hint}</p>}
            <InputError message={form.errors[key]} />
        </div>
    );

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Set up a store" />
            <form onSubmit={submit} className="max-w-3xl p-4">
                <Card>
                    <CardContent className="grid gap-4 pt-6 sm:grid-cols-2">
                        {text('owner', 'Stockist (member username)', 'A member with a paid package')}
                        {text('sponsor', 'Stockist sponsor (optional)', "Gets the sponsor bonus; blank uses the member's own sponsor")}
                        <div className="grid gap-1.5">
                            <Label htmlFor="kind">Kind of store</Label>
                            <select
                                id="kind"
                                className="border-input bg-background h-10 rounded-md border px-3 text-sm"
                                value={form.data.kind}
                                onChange={(e) => form.setData('kind', e.target.value)}
                            >
                                {kinds.map((k) => (
                                    <option key={k.key} value={k.key}>
                                        {k.name} ({formatKobo(k.deposit)} deposit)
                                    </option>
                                ))}
                            </select>
                        </div>
                        {form.data.kind === 'sub' && (
                            <div className="grid gap-1.5">
                                <Label htmlFor="mega_store_id">Collects from (Mega store)</Label>
                                <select
                                    id="mega_store_id"
                                    className="border-input bg-background h-10 rounded-md border px-3 text-sm"
                                    value={form.data.mega_store_id}
                                    onChange={(e) => form.setData('mega_store_id', Number(e.target.value))}
                                >
                                    {megas.length === 0 && <option value={0}>No active Mega store yet</option>}
                                    {megas.map((m) => (
                                        <option key={m.id} value={m.id}>
                                            {m.name}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={form.errors.mega_store_id} />
                            </div>
                        )}
                        {text('name', 'Store name')}
                        {text('state', 'State')}
                        {text('address', 'Address')}
                        {text('phone', 'Phone')}
                        <Button className="w-fit" disabled={form.processing}>
                            Set up the store
                        </Button>
                    </CardContent>
                </Card>
            </form>
        </AdminLayout>
    );
}
