import { type PvLogRow, pv, pvSourceName } from '@/lib/network';
import { DataTable } from './data-table';

const signed = (hundredths: number) => (hundredths < 0 ? `−${pv(-hundredths)}` : `+${pv(hundredths)}`);

/** Every PV a member received, and what matches used, newest first. */
export function PvLogTable({ rows }: { rows: PvLogRow[] }) {
    return (
        <DataTable
            rows={rows}
            rowKey={(r) => r.id}
            emptyTitle="No PV yet"
            emptyDescription="PV arrives here when members below you join, upgrade or buy."
            columns={[
                { key: 'at', header: 'Date', cell: (r) => r.at, className: 'whitespace-nowrap' },
                {
                    key: 'what',
                    header: 'From',
                    cell: (r) => (
                        <>
                            <span className="font-medium">{r.memo?.replace('PV from ', '') ?? '—'}</span>
                            <span className="text-muted-foreground block text-xs">{pvSourceName(r.source)}</span>
                        </>
                    ),
                },
                { key: 'leg', header: 'Leg', cell: (r) => (r.leg === 'L' ? 'Left' : r.leg === 'R' ? 'Right' : r.leg === 'both' ? 'Each leg' : '—') },
                { key: 'pairing', header: 'Pairing PV', align: 'right', cell: (r) => (r.pairing ? signed(r.pairing) : '—') },
                { key: 'lifetime', header: 'Lifetime PV', align: 'right', cell: (r) => (r.lifetime ? signed(r.lifetime) : '—') },
            ]}
        />
    );
}
