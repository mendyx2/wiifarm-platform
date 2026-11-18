import { MatchTable } from '@/components/wf/match-table';
import { Pagination, type Paginated } from '@/components/wf/pagination';
import { PvLogTable } from '@/components/wf/pv-log-table';
import { PvSummary } from '@/components/wf/pv-summary';
import AppLayout from '@/layouts/app-layout';
import { type MatchRow } from '@/lib/matching';
import { formatKobo } from '@/lib/money';
import { type PvLogRow, type PvTotals } from '@/lib/network';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'My team', href: '/team' },
    { title: 'PV log', href: '/team/pv' },
];

type Props = {
    pv: PvTotals | null;
    log: Paginated<PvLogRow>;
    package: { name: string; cap: number; per_match: number } | null;
    matches: (MatchRow & { day: string })[];
};

export default function TeamPv({ pv, log, package: pkg, matches }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="PV log" />
            <div className="space-y-6 p-4">
                <PvSummary totals={pv} />
                <section className="space-y-2">
                    <h2 className="font-medium">Matching bonus</h2>
                    <p className="text-muted-foreground text-sm">
                        Every night, each 50 PV on your left leg paired with 50 PV on your right leg makes one match.
                        {pkg && ` On ${pkg.name} a match pays ${formatKobo(pkg.per_match)}, up to ${pkg.cap} matches a day.`} What is left carries
                        forward.
                    </p>
                    <MatchTable
                        rows={matches}
                        emptyTitle="No matches yet"
                        lead={[{ key: 'day', header: 'Day', cell: (m) => m.day, className: 'whitespace-nowrap' }]}
                    />
                </section>
                <section className="space-y-2">
                    <h2 className="font-medium">Every PV movement</h2>
                    <PvLogTable rows={log.data} />
                    <Pagination page={log} />
                </section>
            </div>
        </AppLayout>
    );
}
