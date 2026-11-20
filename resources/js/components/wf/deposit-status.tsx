import { Badge } from '@/components/ui/badge';

const labels: Record<string, [string, 'warning' | 'success' | 'danger']> = {
    pending: ['Being checked', 'warning'],
    approved: ['Added', 'success'],
    rejected: ['Not approved', 'danger'],
};

/** The state of a bank-transfer deposit. */
export function DepositStatus({ status }: { status: string }) {
    const [label, variant] = labels[status] ?? [status, 'warning'];
    return <Badge variant={variant}>{label}</Badge>;
}
