import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/wf/data-table';
import AdminLayout from '@/layouts/admin-layout';
import { formatKobo } from '@/lib/money';
import { pv } from '@/lib/network';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';

type Row = {
    id: number;
    name: string;
    sku: string;
    manufacturer: string;
    member_price: number;
    public_price: number;
    pv: number;
    units_per_pack: number;
    is_active: boolean;
    is_pack_product: boolean;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin' },
    { title: 'Products', href: '/admin/products' },
];

export default function Products({ products }: { products: Row[] }) {
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
                                <Link href={route('admin.products.edit', p.id)} className="group block">
                                    <span className="font-medium group-hover:underline">{p.name}</span>
                                    <span className="text-muted-foreground block text-xs">
                                        {p.sku} · {p.manufacturer}
                                    </span>
                                    <span className="mt-1 flex gap-1">
                                        {p.is_pack_product && <Badge variant="gold">In every package</Badge>}
                                        {!p.is_active && <Badge variant="outline">Not on sale</Badge>}
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
            </div>
        </AdminLayout>
    );
}
