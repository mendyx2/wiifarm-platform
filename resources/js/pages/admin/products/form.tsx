import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { ProductImage } from '@/components/wf/product-image';
import AdminLayout from '@/layouts/admin-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

type Editing = {
    id: number;
    name: string;
    sku: string;
    manufacturer_id: number;
    category_id: number | null;
    description: string;
    member_price: string;
    public_price: string;
    pv: string;
    units_per_pack: number;
    is_active: boolean;
    image: string | null;
};

function ProductPhoto({ product }: { product: Editing }) {
    const form = useForm<{ photo: File | null }>({ photo: null });
    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('admin.products.photo', product.id), { preserveScroll: true, forceFormData: true, onSuccess: () => form.reset() });
    };

    return (
        <Card className="py-0">
            <CardContent className="grid gap-4 p-5 sm:grid-cols-[12rem_1fr] sm:items-start">
                <ProductImage src={product.image} name={product.name} className="border" />
                <form onSubmit={submit} className="grid gap-3">
                    <div>
                        <h2 className="font-semibold">Photo</h2>
                        <p className="text-muted-foreground text-sm">
                            Shown in the public shop and the members' shop. JPEG, PNG or WebP, up to 3 MB.
                        </p>
                    </div>
                    <Input
                        id="photo"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        onChange={(e) => form.setData('photo', e.target.files?.[0] ?? null)}
                    />
                    <InputError message={form.errors.photo} />
                    <div className="flex flex-wrap gap-2">
                        <Button disabled={!form.data.photo || form.processing}>{product.image ? 'Replace the photo' : 'Upload the photo'}</Button>
                        {product.image && (
                            <Button
                                type="button"
                                variant="ghost"
                                className="text-destructive"
                                onClick={() => router.delete(route('admin.products.photo.remove', product.id), { preserveScroll: true })}
                            >
                                Remove
                            </Button>
                        )}
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}

export default function ProductForm({
    product,
    manufacturers,
    categories,
}: {
    product: Editing | null;
    manufacturers: { id: number; name: string }[];
    categories: { id: number; name: string }[];
}) {
    const isNew = product === null;
    const form = useForm({
        name: product?.name ?? '',
        sku: product?.sku ?? '',
        manufacturer_id: product?.manufacturer_id ?? manufacturers[0]?.id ?? 0,
        category_id: product?.category_id ?? null,
        description: product?.description ?? '',
        member_price: product?.member_price ?? '1500.00',
        public_price: product?.public_price ?? '2000.00',
        pv: product?.pv ?? '1.50',
        units_per_pack: product?.units_per_pack ?? 10,
        is_active: product?.is_active ?? true,
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/admin' },
        { title: 'Products', href: '/admin/products' },
        { title: isNew ? 'Add a product' : product.name, href: isNew ? '/admin/products/create' : `/admin/products/${product.id}` },
    ];

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (isNew) form.post(route('admin.products.store'));
        else form.put(route('admin.products.update', product.id), { preserveScroll: true, onSuccess: () => form.setDefaults() });
    };

    const field = (key: 'name' | 'sku' | 'member_price' | 'public_price' | 'pv', label: string, hint?: string) => (
        <div className="grid gap-1.5">
            <Label htmlFor={key}>{label}</Label>
            <Input id={key} value={form.data[key]} onChange={(e) => form.setData(key, e.target.value)} />
            {hint && !form.errors[key] && <p className="text-muted-foreground text-xs">{hint}</p>}
            <InputError message={form.errors[key]} />
        </div>
    );

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={isNew ? 'Add a product' : product.name} />
            <form onSubmit={submit} className="max-w-3xl p-4">
                <Card>
                    <CardContent className="grid gap-4 pt-6 sm:grid-cols-2">
                        {field('name', 'Product name')}
                        {field('sku', 'Product code (SKU)', 'Letters, digits and dashes')}
                        <div className="grid gap-1.5 sm:col-span-2">
                            <Label htmlFor="category_id">Category</Label>
                            <select
                                id="category_id"
                                className="border-input bg-background h-10 rounded-md border px-3 text-sm"
                                value={form.data.category_id ?? ''}
                                onChange={(e) => form.setData('category_id', e.target.value ? Number(e.target.value) : null)}
                            >
                                <option value="">No category</option>
                                {categories.map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={form.errors.category_id} />
                        </div>
                        <div className="grid gap-1.5 sm:col-span-2">
                            <Label htmlFor="manufacturer_id">Manufacturer</Label>
                            <select
                                id="manufacturer_id"
                                className="border-input bg-background h-10 rounded-md border px-3 text-sm"
                                value={form.data.manufacturer_id}
                                onChange={(e) => form.setData('manufacturer_id', Number(e.target.value))}
                            >
                                {manufacturers.map((m) => (
                                    <option key={m.id} value={m.id}>
                                        {m.name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={form.errors.manufacturer_id} />
                        </div>
                        <div className="grid gap-1.5 sm:col-span-2">
                            <Label htmlFor="description">Description (optional)</Label>
                            <Textarea
                                id="description"
                                rows={3}
                                value={form.data.description}
                                onChange={(e) => form.setData('description', e.target.value)}
                            />
                        </div>
                        {field('member_price', 'Member price a unit (₦)', 'What members pay; half goes to the manufacturer')}
                        {field('public_price', 'Public price a unit (₦)', 'What the public shop charges')}
                        {field('pv', 'PV a unit', 'For example 1.5')}
                        <div className="grid gap-1.5">
                            <Label htmlFor="units_per_pack">Units in a pack</Label>
                            <Input
                                id="units_per_pack"
                                inputMode="numeric"
                                value={form.data.units_per_pack}
                                onChange={(e) => form.setData('units_per_pack', Number(e.target.value.replace(/\D/g, '')) || 0)}
                            />
                            <InputError message={form.errors.units_per_pack} />
                        </div>
                        <div className="flex items-center gap-3 sm:col-span-2">
                            <Checkbox id="is_active" checked={form.data.is_active} onCheckedChange={(v) => form.setData('is_active', v === true)} />
                            <Label htmlFor="is_active">On sale</Label>
                        </div>
                        <Button className="w-fit" disabled={form.processing || (!isNew && !form.isDirty)}>
                            {isNew ? 'Add product' : 'Save product'}
                        </Button>
                    </CardContent>
                </Card>
            </form>
            {!isNew && (
                <div className="max-w-3xl px-4 pb-4">
                    <ProductPhoto product={product} />
                </div>
            )}
        </AdminLayout>
    );
}
