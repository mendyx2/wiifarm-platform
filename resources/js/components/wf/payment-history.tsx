import { Badge } from '@/components/ui/badge';
import { DataTable } from '@/components/wf/data-table';
import { formatKobo } from '@/lib/money';

export type PaymentRow = {
    id: number;
    what: string;
    amount: number;
    reference: string;
    status: 'pending' | 'paid' | 'failed' | 'mismatch';
    note: string | null;
    at: string;
};

export function PaymentStatus({ status }: { status: PaymentRow['status'] }) {
    if (status === 'paid') return <Badge variant="success">Paid</Badge>;
    if (status === 'failed') return <Badge variant="danger">Not completed</Badge>;
    if (status === 'mismatch') return <Badge variant="danger">Being checked</Badge>;
    return <Badge variant="warning">Waiting for Paystack</Badge>;
}

/** A member's recent online payments. */
export function PaymentHistory({ rows }: { rows: PaymentRow[] }) {
    return (
        <DataTable
            rows={rows}
            rowKey={(p) => p.id}
            emptyTitle="No online payments yet"
            columns={[
                { key: 'at', header: 'Date', cell: (p) => p.at, className: 'whitespace-nowrap' },
                {
                    key: 'what',
                    header: 'For',
                    cell: (p) => (
                        <>
                            {p.what}
                            <span className="text-muted-foreground block font-mono text-xs">{p.reference}</span>
                            {p.note && <span className="text-muted-foreground block text-xs">{p.note}</span>}
                        </>
                    ),
                },
                { key: 'amount', header: 'Amount', align: 'right', cell: (p) => formatKobo(p.amount), className: 'whitespace-nowrap' },
                { key: 'status', header: 'Status', cell: (p) => <PaymentStatus status={p.status} /> },
            ]}
        />
    );
}

/** Shown when no Paystack key is set. */
export function OfflineNotice() {
    return (
        <div role="alert" className="border-warning/40 bg-warning-soft text-warning rounded-lg border p-4 text-sm">
            Online payment is not set up yet. Please try again later.
        </div>
    );
}
