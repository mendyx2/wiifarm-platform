import { Badge } from '@/components/ui/badge';
import { DataTable } from '@/components/wf/data-table';
import AdminLayout from '@/layouts/admin-layout';
import { formatKobo } from '@/lib/money';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

type Row = {
    id: number;
    name: string;
    price: number;
    pv: number;
    pv_value: number;
    product_packs: number;
    direct_bonus_percent: string;
    match_percent: string;
    per_match: number;
    daily_cap_matches: number;
    unilevel_generations: number;
    upgradable: boolean;
    is_active: boolean;
    members: number;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin' },
    { title: 'Packages', href: '/admin/packages' },
];

export default function Packages({ packages }: { packages: Row[] }) {
    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Packages" />
            <div className="space-y-4 p-4">
                <p className="text-muted-foreground text-sm">
                    The seven packages of the compensation plan. Bonuses are worked out on the PV value (PV × ₦600); one match is 50 PV on each leg,
                    worth ₦30,000.
                </p>
                <DataTable
                    rows={packages}
                    rowKey={(p) => p.id}
                    columns={[
                        {
                            key: 'name',
                            header: 'Package',
                            cell: (p) => (
                                <>
                                    <span className="font-medium">{p.name}</span>
                                    <span className="mt-1 flex gap-1">
                                        {!p.is_active && <Badge variant="outline">Not on offer</Badge>}
                                        {!p.upgradable && <Badge variant="outline">No upgrades</Badge>}
                                    </span>
                                </>
                            ),
                        },
                        { key: 'price', header: 'Price', align: 'right', cell: (p) => formatKobo(p.price) },
                        { key: 'pv', header: 'PV', align: 'right', cell: (p) => p.pv },
                        { key: 'value', header: 'PV value', align: 'right', cell: (p) => formatKobo(p.pv_value) },
                        { key: 'packs', header: 'Packs', align: 'right', cell: (p) => p.product_packs },
                        { key: 'direct', header: 'Direct bonus', align: 'right', cell: (p) => `${Number(p.direct_bonus_percent)}%` },
                        {
                            key: 'match',
                            header: 'Per match',
                            align: 'right',
                            cell: (p) => `${formatKobo(p.per_match)} (${Number(p.match_percent)}%)`,
                        },
                        { key: 'cap', header: 'Daily cap', align: 'right', cell: (p) => `${p.daily_cap_matches} matches` },
                        { key: 'uni', header: 'Unilevel', align: 'right', cell: (p) => `1–${p.unilevel_generations}` },
                        { key: 'members', header: 'Members', align: 'right', cell: (p) => p.members },
                    ]}
                />
            </div>
        </AdminLayout>
    );
}
