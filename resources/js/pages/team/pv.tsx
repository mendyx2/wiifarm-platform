import { Pagination, type Paginated } from '@/components/wf/pagination';
import { PvLogTable } from '@/components/wf/pv-log-table';
import { PvSummary } from '@/components/wf/pv-summary';
import AppLayout from '@/layouts/app-layout';
import { type PvLogRow, type PvTotals } from '@/lib/network';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'My team', href: '/team' },
    { title: 'PV log', href: '/team/pv' },
];

export default function TeamPv({ pv, log }: { pv: PvTotals | null; log: Paginated<PvLogRow> }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="PV log" />
            <div className="space-y-6 p-4">
                <PvSummary totals={pv} />
                <section className="space-y-2">
                    <h2 className="font-medium">Every PV credit</h2>
                    <PvLogTable rows={log.data} />
                    <Pagination page={log} />
                </section>
            </div>
        </AppLayout>
    );
}
