import { Badge } from '@/components/ui/badge';
import { DataTable, type Column } from '@/components/wf/data-table';
import { type MatchRow } from '@/lib/matching';
import { formatKobo, formatPv } from '@/lib/money';

/** The columns every matching table shares: PV before the run, matches, and what was earned. */
export function matchColumns<Row extends MatchRow>(): Column<Row>[] {
    return [
        { key: 'package', header: 'Package', cell: (m) => m.package },
        {
            key: 'pv',
            header: 'Pairing PV (left / right)',
            align: 'right',
            cell: (m) => `${formatPv(m.left_pv / 100)} / ${formatPv(m.right_pv / 100)}`,
            className: 'whitespace-nowrap',
        },
        {
            key: 'matches',
            header: 'Matches',
            align: 'right',
            cell: (m) => (
                <>
                    {m.matches}
                    {m.capped && (
                        <Badge variant="warning" className="ml-2">
                            Daily cap
                        </Badge>
                    )}
                </>
            ),
        },
        {
            key: 'amount',
            header: 'Earned',
            align: 'right',
            cell: (m) => (
                <>
                    {formatKobo(m.amount)}
                    {m.autoship > 0 && <span className="text-muted-foreground block text-xs">{formatKobo(m.autoship)} to autoship</span>}
                </>
            ),
            className: 'whitespace-nowrap',
        },
        {
            key: 'status',
            header: 'Status',
            cell: (m) => (m.status === 'paid' ? <Badge variant="success">Paid</Badge> : <Badge variant="warning">Held until the line refills</Badge>),
        },
    ];
}

export function MatchTable<Row extends MatchRow>({ rows, lead = [], emptyTitle }: { rows: Row[]; lead?: Column<Row>[]; emptyTitle: string }) {
    return <DataTable rows={rows} rowKey={(m) => m.id} emptyTitle={emptyTitle} columns={[...lead, ...matchColumns<Row>()]} />;
}
