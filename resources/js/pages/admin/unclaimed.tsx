import { DataTable } from '@/components/wf/data-table';
import { Pagination, type Paginated } from '@/components/wf/pagination';
import { StatCard } from '@/components/wf/stat-card';
import AdminLayout from '@/layouts/admin-layout';
import { formatKobo } from '@/lib/money';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

type Filters = { pool: string | null; bonus: string | null; reason: string | null };
type Group = { key: string; count: number; amount: number };
type Inflow = {
    id: number;
    at: string;
    pool: string;
    bonus: string;
    generation: number | null;
    reason: string;
    amount: number;
    memo: string | null;
    beneficiary: string | null;
    source: string | null;
    transaction: number;
};
type Labels = { pools: Record<string, string>; bonuses: Record<string, string>; reasons: Record<string, string> };

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin' },
    { title: 'Unclaimed bonuses', href: '/admin/unclaimed' },
];

/** A link to this page with one filter changed (clicking the active one clears it). */
function filterHref(filters: Filters, key: keyof Filters, value: string) {
    const next = { ...filters, [key]: filters[key] === value ? null : value };
    return route('admin.unclaimed.index', Object.fromEntries(Object.entries(next).filter(([, v]) => v !== null)));
}

function Breakdown({
    title,
    groups,
    label,
    filters,
    filterKey,
}: {
    title: string;
    groups: Group[];
    label: Record<string, string>;
    filters: Filters;
    filterKey: keyof Filters;
}) {
    return (
        <section className="bg-card shadow-soft rounded-xl border p-4">
            <h2 className="mb-2 font-semibold">{title}</h2>
            {groups.length === 0 ? (
                <p className="text-muted-foreground text-sm">Nothing yet.</p>
            ) : (
                <ul className="space-y-1 text-sm">
                    {groups.map((g) => (
                        <li key={g.key}>
                            <Link
                                href={filterHref(filters, filterKey, g.key)}
                                preserveScroll
                                className={cn(
                                    'hover:bg-muted flex justify-between gap-3 rounded-md px-2 py-1',
                                    filters[filterKey] === g.key && 'bg-accent text-accent-foreground',
                                )}
                            >
                                <span>
                                    {label[g.key] ?? g.key} <span className="text-muted-foreground text-xs">({g.count})</span>
                                </span>
                                <span className="tabular-nums">{formatKobo(g.amount)}</span>
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

export default function Unclaimed({
    filters,
    labels,
    total,
    byPool,
    byBonus,
    byReason,
    inflows,
}: {
    filters: Filters;
    labels: Labels;
    total: number;
    byPool: Group[];
    byBonus: Group[];
    byReason: Group[];
    inflows: Paginated<Inflow>;
}) {
    const filtered = filters.pool || filters.bonus || filters.reason;

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Unclaimed bonuses" />
            <div className="space-y-4 p-4">
                <p className="text-muted-foreground max-w-3xl text-sm">
                    Every bonus share nobody could receive (for example a generation beyond the top of the tree, a banned upline or the company
                    account) goes to its pool's Unclaimed Bonus Reserve, never to another pool. Each inflow below says which bonus it came from, why
                    it could not be paid, who would have received it, and whose joining, purchase, store or award it came from. Click a line to
                    filter.
                </p>
                <div className="grid gap-4 sm:grid-cols-2">
                    <StatCard label={filtered ? 'In the reserve (filtered)' : 'In the reserve, all pools'} value={formatKobo(total)} tone="gold" />
                    {filtered && (
                        <div className="flex items-center">
                            <Link
                                href={route('admin.unclaimed.index')}
                                className="text-primary text-sm font-medium underline-offset-4 hover:underline"
                            >
                                Clear the filters
                            </Link>
                        </div>
                    )}
                </div>
                <div className="grid gap-4 lg:grid-cols-3">
                    <Breakdown title="By pool" groups={byPool} label={labels.pools} filters={filters} filterKey="pool" />
                    <Breakdown title="By bonus" groups={byBonus} label={labels.bonuses} filters={filters} filterKey="bonus" />
                    <Breakdown title="By reason" groups={byReason} label={labels.reasons} filters={filters} filterKey="reason" />
                </div>
                <DataTable
                    rows={inflows.data}
                    rowKey={(i) => i.id}
                    emptyTitle="Nothing in the reserve"
                    columns={[
                        { key: 'at', header: 'Date', cell: (i) => i.at, className: 'whitespace-nowrap' },
                        {
                            key: 'bonus',
                            header: 'Bonus',
                            cell: (i) => (
                                <>
                                    {labels.bonuses[i.bonus] ?? i.bonus}
                                    {i.generation && <span className="text-muted-foreground"> · generation {i.generation}</span>}
                                    <span className="text-muted-foreground block text-xs">{labels.pools[i.pool] ?? i.pool}</span>
                                </>
                            ),
                        },
                        {
                            key: 'reason',
                            header: 'Why it was not paid',
                            cell: (i) => (
                                <>
                                    {labels.reasons[i.reason] ?? i.reason}
                                    {i.beneficiary && (
                                        <span className="text-muted-foreground block text-xs">would have gone to @{i.beneficiary}</span>
                                    )}
                                </>
                            ),
                        },
                        {
                            key: 'source',
                            header: 'Came from',
                            cell: (i) => (
                                <>
                                    {i.source ? `@${i.source}` : '—'}
                                    {i.memo && <span className="text-muted-foreground block text-xs">{i.memo}</span>}
                                </>
                            ),
                        },
                        { key: 'amount', header: 'Amount', align: 'right', cell: (i) => formatKobo(i.amount), className: 'whitespace-nowrap' },
                    ]}
                />
                <Pagination page={inflows} />
            </div>
        </AdminLayout>
    );
}
