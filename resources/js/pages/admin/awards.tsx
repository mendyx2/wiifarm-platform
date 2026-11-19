import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { AwardStatus, type AwardRow } from '@/components/wf/award-status';
import { DataTable } from '@/components/wf/data-table';
import { Pagination, type Paginated } from '@/components/wf/pagination';
import AdminLayout, { useAdminCan } from '@/layouts/admin-layout';
import { formatKobo } from '@/lib/money';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

type Row = AwardRow & { member: { id: number; username: string; name: string }; decided_by: string | null };

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin' },
    { title: 'Awards', href: '/admin/awards' },
];

const tabs: [string, string][] = [
    ['pending', 'Waiting'],
    ['held', 'Held'],
    ['gift', 'Gifts to deliver'],
    ['all', 'All'],
];

function Decide({ row }: { row: Row }) {
    const [refusing, setRefusing] = useState(false);
    const form = useForm({ reason: '' });

    if (refusing) {
        return (
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(route('admin.awards.reject', row.id), { preserveScroll: true });
                }}
                className="flex flex-wrap items-start gap-2"
            >
                <div>
                    <Input
                        aria-label="Reason"
                        placeholder="Reason"
                        className="h-8 w-44"
                        value={form.data.reason}
                        onChange={(e) => form.setData('reason', e.target.value)}
                    />
                    <InputError message={form.errors.reason} />
                </div>
                <Button size="sm" variant="destructive" disabled={!form.data.reason.trim() || form.processing}>
                    Refuse
                </Button>
            </form>
        );
    }

    return (
        <div className="flex flex-wrap gap-2">
            <Button size="sm" onClick={() => router.post(route('admin.awards.approve', row.id), {}, { preserveScroll: true })}>
                Approve {row.choice === 'gift' && row.gift ? `(${row.gift})` : `(${formatKobo(row.amount)})`}
            </Button>
            <Button size="sm" variant="ghost" className="text-destructive" onClick={() => setRefusing(true)}>
                Refuse
            </Button>
        </div>
    );
}

export default function AdminAwards({ awards, status }: { awards: Paginated<Row>; status: string }) {
    const can = useAdminCan();
    const { errors } = usePage().props as { errors: Record<string, string> };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Awards" />
            <div className="space-y-4 p-4">
                <p className="text-muted-foreground max-w-3xl text-sm">
                    Members who qualified in the nightly check. Approving pays the cash, or sets the gift's value aside if the member chose the gift,
                    and pays the Upline Celebrated Bonus (5%, 2%, 1%) on a recognition award. Whatever a pool cannot cover yet is held and paid when
                    it refills.
                </p>
                <InputError message={errors.award} />
                <nav className="flex flex-wrap gap-2" aria-label="Filter by status">
                    {tabs.map(([value, label]) => (
                        <Link
                            key={value}
                            href={route('admin.awards.index', value === 'pending' ? {} : { status: value })}
                            className={cn(
                                'rounded-full border px-3 py-1 text-sm',
                                status === value ? 'bg-primary text-primary-foreground border-primary' : 'hover:bg-muted',
                            )}
                        >
                            {label}
                        </Link>
                    ))}
                </nav>
                <DataTable
                    rows={awards.data}
                    rowKey={(a) => a.id}
                    emptyTitle={status === 'pending' ? 'No awards waiting' : 'No awards'}
                    columns={[
                        { key: 'at', header: 'Qualified', cell: (a) => a.at, className: 'whitespace-nowrap' },
                        {
                            key: 'member',
                            header: 'Member',
                            cell: (a) => (
                                <Link href={route('admin.members.show', a.member.id)} className="underline-offset-4 hover:underline">
                                    <span className="font-medium">{a.member.name || a.member.username}</span>{' '}
                                    <span className="text-muted-foreground text-xs">@{a.member.username}</span>
                                </Link>
                            ),
                        },
                        {
                            key: 'award',
                            header: 'Award',
                            cell: (a) => (
                                <>
                                    <span className="font-medium">{a.name}</span>
                                    <span className="text-muted-foreground block text-xs">
                                        {a.kind === 'rank'
                                            ? `${new Intl.NumberFormat('en-NG').format(a.measure)} PV on the lesser leg`
                                            : `${new Intl.NumberFormat('en-NG').format(a.measure)} PV of team repurchases`}
                                    </span>
                                </>
                            ),
                        },
                        {
                            key: 'value',
                            header: 'Value',
                            align: 'right',
                            cell: (a) => (
                                <>
                                    {formatKobo(a.amount)}
                                    {a.choice === 'gift' && a.gift && (
                                        <span className="text-muted-foreground block text-xs">as the gift: {a.gift}</span>
                                    )}
                                </>
                            ),
                            className: 'whitespace-nowrap',
                        },
                        {
                            key: 'status',
                            header: 'Status',
                            cell: (a) => (
                                <>
                                    <AwardStatus status={a.status} />
                                    {a.decided_by && (
                                        <span className="text-muted-foreground block text-xs">
                                            by @{a.decided_by}, {a.decided_at}
                                        </span>
                                    )}
                                    {a.reason && <span className="text-muted-foreground block text-xs">{a.reason}</span>}
                                </>
                            ),
                        },
                        ...(can('awards.decide')
                            ? [
                                  {
                                      key: 'act',
                                      header: '',
                                      cell: (a: Row) =>
                                          a.status === 'pending' ? (
                                              <Decide row={a} />
                                          ) : a.status === 'gift' ? (
                                              <Button
                                                  size="sm"
                                                  variant="secondary"
                                                  onClick={() => router.post(route('admin.awards.delivered', a.id), {}, { preserveScroll: true })}
                                              >
                                                  Mark delivered
                                              </Button>
                                          ) : null,
                                  },
                              ]
                            : []),
                    ]}
                />
                <Pagination page={awards} />
            </div>
        </AdminLayout>
    );
}
