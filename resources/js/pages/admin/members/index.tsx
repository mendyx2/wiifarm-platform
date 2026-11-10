import { KycBadge, MemberStatusBadge, PaidBadge } from '@/components/admin/badges';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { DataTable, type Column } from '@/components/wf/data-table';
import { Pagination, type Paginated } from '@/components/wf/pagination';
import AdminLayout from '@/layouts/admin-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

type Row = {
    id: number;
    username: string;
    name: string;
    email: string;
    mobile: string;
    status: string;
    kyc_status: string;
    paid: boolean;
    withdrawals_blocked: boolean;
    joined: string;
};

type Filters = { q?: string; status?: string; kyc?: string; paid?: string };

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin' },
    { title: 'Members', href: '/admin/members' },
];

const selects: { key: keyof Filters; label: string; options: [string, string][] }[] = [
    {
        key: 'status',
        label: 'Any status',
        options: [
            ['active', 'Active'],
            ['banned', 'Banned'],
        ],
    },
    {
        key: 'kyc',
        label: 'Any KYC state',
        options: [
            ['unverified', 'Not verified'],
            ['pending', 'Under review'],
            ['verified', 'Verified'],
        ],
    },
    {
        key: 'paid',
        label: 'Paid and free',
        options: [
            ['paid', 'Paid'],
            ['free', 'Free'],
        ],
    },
];

const columns: Column<Row>[] = [
    {
        key: 'member',
        header: 'Member',
        cell: (m) => (
            <Link href={route('admin.members.show', m.id)} className="group block">
                <span className="font-medium group-hover:underline">{m.name}</span>
                <span className="text-muted-foreground block text-xs">@{m.username}</span>
            </Link>
        ),
    },
    {
        key: 'contact',
        header: 'Contact',
        cell: (m) => (
            <>
                <span className="block">{m.email}</span>
                <span className="text-muted-foreground block text-xs">{m.mobile}</span>
            </>
        ),
    },
    {
        key: 'state',
        header: 'State',
        cell: (m) => (
            <div className="flex flex-wrap gap-1">
                <MemberStatusBadge status={m.status} />
                <PaidBadge paid={m.paid} />
                <KycBadge status={m.kyc_status} />
                {m.withdrawals_blocked && <Badge variant="warning">Withdrawals blocked</Badge>}
            </div>
        ),
    },
    { key: 'joined', header: 'Joined', cell: (m) => m.joined, className: 'whitespace-nowrap' },
];

export default function MembersIndex({ members, filters }: { members: Paginated<Row>; filters: Filters }) {
    const [q, setQ] = useState(filters.q ?? '');

    const apply = (next: Filters) => {
        const query = Object.fromEntries(Object.entries({ ...filters, ...next }).filter(([, v]) => v));
        router.get(route('admin.members.index'), query, { preserveState: true, preserveScroll: true, replace: true });
    };

    const search: FormEventHandler = (e) => {
        e.preventDefault();
        apply({ q });
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Members" />
            <div className="space-y-4 p-4">
                <div className="flex flex-wrap items-end gap-3">
                    <form onSubmit={search} className="flex min-w-64 flex-1 gap-2">
                        <Input
                            aria-label="Search members"
                            placeholder="Username, name, email or phone"
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                        />
                        <Button type="submit" variant="secondary">
                            <Search className="size-4" />
                            Search
                        </Button>
                    </form>
                    {selects.map((s) => (
                        <select
                            key={s.key}
                            aria-label={s.label}
                            className="border-input bg-background h-10 rounded-md border px-3 text-sm"
                            value={filters[s.key] ?? ''}
                            onChange={(e) => apply({ [s.key]: e.target.value })}
                        >
                            <option value="">{s.label}</option>
                            {s.options.map(([value, label]) => (
                                <option key={value} value={value}>
                                    {label}
                                </option>
                            ))}
                        </select>
                    ))}
                </div>

                <DataTable
                    columns={columns}
                    rows={members.data}
                    rowKey={(m) => m.id}
                    emptyTitle="No members found"
                    emptyDescription="Try a different search, or clear the filters."
                />
                <Pagination page={members} />
            </div>
        </AdminLayout>
    );
}
