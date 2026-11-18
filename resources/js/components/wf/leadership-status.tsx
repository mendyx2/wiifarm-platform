import { Badge } from '@/components/ui/badge';

export function LeadershipStatus({ status }: { status: string }) {
    if (status === 'paid') return <Badge variant="success">Paid</Badge>;
    if (status === 'held') return <Badge variant="warning">Held until the line refills</Badge>;
    return <Badge variant="secondary">Nothing to pay</Badge>;
}
