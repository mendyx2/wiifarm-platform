import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/admin-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

type MethodRow = {
    id: number;
    name: string;
    is_active: boolean;
    min_amount: string;
    max_amount: string;
    fixed_charge: string;
    percent_charge: string;
    fields: string;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin' },
    { title: 'Withdrawals', href: '/admin/withdrawals' },
    { title: 'Methods', href: '/admin/withdrawals/methods' },
];

function MethodForm({ method }: { method: MethodRow | null }) {
    const form = useForm({
        name: method?.name ?? '',
        min_amount: method?.min_amount ?? '1000.00',
        max_amount: method?.max_amount ?? '5000000.00',
        fixed_charge: method?.fixed_charge ?? '0.00',
        percent_charge: method?.percent_charge ?? '0.00',
        fields: method?.fields ?? 'Bank name, Account number, Account name',
        is_active: method?.is_active ?? true,
    });
    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (method) form.put(route('admin.withdrawals.methods.update', method.id), { preserveScroll: true });
        else form.post(route('admin.withdrawals.methods.store'), { preserveScroll: true, onSuccess: () => form.reset() });
    };
    const id = (k: string) => `${method?.id ?? 'new'}-${k}`;
    const text = (k: 'name' | 'min_amount' | 'max_amount' | 'fixed_charge' | 'percent_charge' | 'fields', label: string, wide = false) => (
        <div className={wide ? 'grid gap-1.5 sm:col-span-3' : 'grid gap-1.5'}>
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
                    {text('fixed_charge', 'Fixed charge (₦)')}
                    {text('percent_charge', 'Percentage charge (%)')}
                    <div className="flex items-end gap-3 pb-2">
                        <Checkbox id={id('active')} checked={form.data.is_active} onCheckedChange={(v) => form.setData('is_active', v === true)} />
                        <Label htmlFor={id('active')}>Available</Label>
                    </div>
                    {text('fields', 'Details to ask for (separated by commas)', true)}
                    <Button className="w-fit" disabled={form.processing || (method !== null && !form.isDirty)}>
                        {method ? 'Save' : 'Add method'}
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}

export default function WithdrawalMethods({ methods }: { methods: MethodRow[] }) {
    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Withdrawal methods" />
            <div className="max-w-4xl space-y-4 p-4">
                <p className="text-muted-foreground text-sm">
                    Charges go to the cooperative. Changes are saved in the audit log and apply to new requests.
                </p>
                {methods.map((m) => (
                    <MethodForm key={m.id} method={m} />
                ))}
                <MethodForm method={null} />
            </div>
        </AdminLayout>
    );
}
