import { Badge } from '@/components/ui/badge';

const statuses: Record<string, [string, 'outline' | 'warning' | 'success' | 'danger' | 'info']> = {
    pending: ['Waiting for payment', 'outline'],
    paid: ['Ready to collect', 'info'],
    fulfilled: ['Collected', 'success'],
    cancelled: ['Cancelled', 'danger'],
};

export function OrderStatusBadge({ status }: { status: string }) {
    const [label, variant] = statuses[status] ?? [status, 'outline'];
    return <Badge variant={variant}>{label}</Badge>;
}

const kinds: Record<string, string> = { registration: 'Registration packs', repurchase: 'Repurchase', public: 'Public shop' };

export const orderKindName = (kind: string) => kinds[kind] ?? kind;
