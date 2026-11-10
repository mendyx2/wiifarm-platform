import { type StatementRow, transactionName } from '@/lib/ledger';
import { formatKobo } from '@/lib/money';
import { cn } from '@/lib/utils';
import { DataTable } from './data-table';

/** A wallet statement: newest first, money in and out, and the balance after each line. */
export function StatementTable({ rows, showTransaction = false }: { rows: StatementRow[]; showTransaction?: boolean }) {
    return (
        <DataTable
            rows={rows}
            rowKey={(r) => r.id}
            emptyTitle="Nothing in this wallet yet"
            emptyDescription="Every naira that comes in or goes out will be listed here."
            columns={[
                { key: 'at', header: 'Date', cell: (r) => r.at, className: 'whitespace-nowrap' },
                {
                    key: 'what',
                    header: 'Details',
                    cell: (r) => (
                        <>
                            <span className="font-medium">{transactionName(r.type)}</span>
                            {r.memo && <span className="text-muted-foreground block text-xs">{r.memo}</span>}
                            {showTransaction && <span className="text-muted-foreground block text-xs">Transaction #{r.transaction}</span>}
                        </>
                    ),
                },
                {
                    key: 'amount',
                    header: 'Amount',
                    align: 'right',
                    cell: (r) => (
                        <span className={cn('font-medium', r.amount > 0 ? 'text-success' : 'text-destructive')}>
                            {r.amount > 0 ? '+' : '−'}
                            {formatKobo(Math.abs(r.amount))}
                        </span>
                    ),
                },
                { key: 'balance', header: 'Balance after', align: 'right', cell: (r) => formatKobo(r.balance) },
            ]}
        />
    );
}
