import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { DataTable } from '@/components/wf/data-table';
import { ProductImage } from '@/components/wf/product-image';
import AdminLayout from '@/layouts/admin-layout';
import { formatKobo } from '@/lib/money';
import { pv } from '@/lib/network';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { FormEventHandler } from 'react';

type Row = {
    id: number;
    name: string;
    sku: string;
    manufacturer: string;
    category: string | null;
    member_price: number;
    public_price: number;
    pv: number;
    units_per_pack: number;
    is_active: boolean;
    is_pack_product: boolean;
    image: string | null;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin' },
    { title: 'Products', href: '/admin/products' },
];

type CategoryRow = { id: number; name: string; sort: number; products: number };

function CategoryForm({ row }: { row: CategoryRow | null }) {
    const form = useForm({ name: row?.name ?? '', sort: row?.sort ?? 0 });
    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (row) form.put(route('admin.products.categories.update', row.id), { preserveScroll: true, onSuccess: () => form.setDefaults() });
        else form.post(route('admin.products.categories.store'), { preserveScroll: true, onSuccess: () => form.reset() });
    };

    return (
        <form onSubmit={submit} className="flex flex-wrap items-start gap-2">
            <div>
                <Input
                    aria-label="Category name"
                    placeholder={row ? '' : 'New category'}
                    className="w-48"
                    value={form.data.name}
                    onChange={(e) => form.setData('name', e.target.value)}
                />
                <InputError message={form.errors.name} />
            </div>
            <Input
                aria-label="Place in the list"
                type="number"
                min={0}
                className="w-20"
                value={form.data.sort}
                onChange={(e) => form.setData('sort', Number(e.target.value))}
            />
            <Button size="sm" variant="secondary" disabled={form.processing || !form.isDirty || !form.data.name.trim()}>
                {row ? 'Save' : 'Add'}
            </Button>
            {row && (
                <>
                    <span className="text-muted-foreground self-center text-xs">{row.products} products</span>
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        onClick={() =>
                            confirm(`Delete "${row.name}"? Its products stay, without a category.`) &&
                            router.delete(route('admin.products.categories.destroy', row.id), { preserveScroll: true })
                        }
                    >
                        Delete
                    </Button>
                </>
            )}
        </form>
    );
}

export default function Products({ products, categories }: { products: Row[]; categories: CategoryRow[] }) {
    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Products" />
            <div className="space-y-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <p className="text-muted-foreground text-sm">
                        Each product names its manufacturer, whose account receives the manufacturer's share of every sale.
                    </p>
                    <Button asChild>
                        <Link href={route('admin.products.create')}>
                            <Plus className="size-4" />
                            Add a product
                        </Link>
                    </Button>
                </div>
                <DataTable
                    rows={products}
                    rowKey={(p) => p.id}
                    columns={[
                        {
                            key: 'name',
                            header: 'Product',
                            cell: (p) => (
                                <Link href={route('admin.products.edit', p.id)} className="group flex items-start gap-3">
                                    <ProductImage src={p.image} name={p.name} className="w-16 shrink-0" />
                                    <span className="block">
                                        <span className="font-medium group-hover:underline">{p.name}</span>
                                        <span className="text-muted-foreground block text-xs">
                                            {p.sku} · {p.manufacturer}
                                            {p.category && ` · ${p.category}`}
                                        </span>
                                        <span className="mt-1 flex gap-1">
                                            {p.is_pack_product && <Badge variant="gold">In every package</Badge>}
                                            {!p.is_active && <Badge variant="outline">Not on sale</Badge>}
                                        </span>
                                    </span>
                                </Link>
                            ),
                        },
                        { key: 'member', header: 'Member price', align: 'right', cell: (p) => formatKobo(p.member_price) },
                        { key: 'public', header: 'Public price', align: 'right', cell: (p) => formatKobo(p.public_price) },
                        { key: 'pv', header: 'PV a unit', align: 'right', cell: (p) => pv(p.pv) },
                        { key: 'pack', header: 'Units a pack', align: 'right', cell: (p) => p.units_per_pack },
                    ]}
                />
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Categories</CardTitle>
                        <CardDescription>
                            Groups members and the public can browse by in the shops. The number sets the order (lowest first). Choose a product's
                            category on its page.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {categories.map((c) => (
                            <CategoryForm key={c.id} row={c} />
                        ))}
                        <CategoryForm row={null} />
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
