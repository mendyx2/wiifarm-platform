import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AdminLayout from '@/layouts/admin-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

type MethodRow = { id: number; name: string; instructions: string; is_active: boolean; min_amount: string; max_amount: string };

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin' },
    { title: 'Deposits', href: '/admin/deposits' },
    { title: 'Methods', href: '/admin/deposits/methods' },
];

function MethodForm({ method }: { method: MethodRow | null }) {
    const form = useForm({
        name: method?.name ?? '',
        instructions: method?.instructions ?? '',
        min_amount: method?.min_amount ?? '1000.00',
        max_amount: method?.max_amount ?? '5000000.00',
        is_active: method?.is_active ?? false,
    });
    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (method) form.put(route('admin.deposits.methods.update', method.id), { preserveScroll: true });
        else form.post(route('admin.deposits.methods.store'), { preserveScroll: true, onSuccess: () => form.reset() });
    };
    const id = (k: string) => `${method?.id ?? 'new'}-${k}`;
    const text = (k: 'name' | 'min_amount' | 'max_amount', label: string) => (
        <div className="grid gap-1.5">
            <Label htmlFor={id(k)}>{label}</Label>
            <Input id={id(k)} value={form.data[k]} onChange={(e) => form.setData(k, e.target.value)} />
            <InputError message={form.errors[k]} />
        </div>
    );

    return (
        <Card>
            <CardHeader className="pb-3">
                <CardTitle className="text-base">{method ? method.name : 'Add a method'}</CardTitle>
            </CardHeader>
            <CardContent>
                <form onSubmit={submit} className="grid gap-3 sm:grid-cols-3">
                    {text('name', 'Name')}
                    {text('min_amount', 'Minimum (₦)')}
                    {text('max_amount', 'Maximum (₦)')}
                    <div className="grid gap-1.5 sm:col-span-3">
                        <Label htmlFor={id('instructions')}>What the member sees: where to pay</Label>
                        <Textarea
                            id={id('instructions')}
                            rows={5}
                            value={form.data.instructions}
                            onChange={(e) => form.setData('instructions', e.target.value)}
                        />
                        <InputError message={form.errors.instructions} />
                    </div>
                    <div className="flex items-center gap-3">
                        <Checkbox id={id('active')} checked={form.data.is_active} onCheckedChange={(v) => form.setData('is_active', v === true)} />
                        <Label htmlFor={id('active')}>Available to members</Label>
                    </div>
                    <Button className="w-fit sm:col-start-1" disabled={form.processing || (method !== null && !form.isDirty)}>
                        {method ? 'Save' : 'Add method'}
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}

export default function DepositMethods({ methods }: { methods: MethodRow[] }) {
    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Deposit methods" />
            <div className="max-w-4xl space-y-4 p-4">
                <p className="text-muted-foreground text-sm">
                    Ways to deposit outside Paystack, for example a bank transfer to Wiifarm's account. Members see the text below, pay, and upload
                    their proof. Changes are saved in the audit log.
                </p>
                {methods.map((m) => (
                    <MethodForm key={m.id} method={m} />
                ))}
                <MethodForm method={null} />
            </div>
        </AdminLayout>
    );
}
