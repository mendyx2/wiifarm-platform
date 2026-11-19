import { Badge } from '@/components/ui/badge';

export type AwardRow = {
    id: number;
    kind: 'rank' | 'repurchase';
    name: string;
    amount: number;
    gift: string | null;
    choice: 'cash' | 'gift';
    measure: number;
    status: 'pending' | 'paid' | 'held' | 'gift' | 'delivered' | 'rejected';
    reason: string | null;
    at: string;
    decided_at: string | null;
};

export function AwardStatus({ status }: { status: AwardRow['status'] }) {
    switch (status) {
        case 'pending':
            return <Badge variant="info">Waiting for approval</Badge>;
        case 'paid':
            return <Badge variant="success">Paid</Badge>;
        case 'held':
            return <Badge variant="warning">Approved, held until the pool refills</Badge>;
        case 'gift':
            return <Badge variant="gold">Gift to be delivered</Badge>;
        case 'delivered':
            return <Badge variant="success">Gift delivered</Badge>;
        default:
            return <Badge variant="danger">Refused</Badge>;
    }
}
