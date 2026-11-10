import { Badge } from '@/components/ui/badge';

/** Small coloured labels used across the admin member and KYC screens. */
export function MemberStatusBadge({ status }: { status: string }) {
    return status === 'banned' ? <Badge variant="danger">Banned</Badge> : <Badge variant="success">Active</Badge>;
}

// [label, short label, colour]
const kyc: Record<string, [string, string, 'outline' | 'warning' | 'success' | 'danger']> = {
    unverified: ['KYC not verified', 'Not verified', 'outline'],
    pending: ['KYC under review', 'Under review', 'warning'],
    verified: ['KYC verified', 'Verified', 'success'],
    approved: ['Approved', 'Approved', 'success'],
    rejected: ['Rejected', 'Rejected', 'danger'],
};

export function KycBadge({ status, short = false }: { status: string; short?: boolean }) {
    const [label, shortLabel, variant] = kyc[status] ?? [status, status, 'outline'];
    return <Badge variant={variant}>{short ? shortLabel : label}</Badge>;
}

export function PaidBadge({ paid }: { paid: boolean }) {
    return paid ? <Badge variant="gold">Paid</Badge> : <Badge variant="outline">Free</Badge>;
}
