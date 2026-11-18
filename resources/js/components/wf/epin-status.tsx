import { Badge } from '@/components/ui/badge';

export function EPinStatus({ status }: { status: string }) {
    if (status === 'active') return <Badge variant="success">Unused</Badge>;
    if (status === 'used') return <Badge variant="secondary">Used</Badge>;
    return <Badge variant="danger">Cancelled</Badge>;
}

/** An e-pin code with a button to copy it. */
export function PinCode({ code }: { code: string }) {
    return (
        <span className="inline-flex items-center gap-2">
            <span className="font-mono text-sm tracking-wider">{code}</span>
            <button
                type="button"
                className="text-muted-foreground hover:text-foreground text-xs underline-offset-4 hover:underline"
                onClick={() => navigator.clipboard?.writeText(code)}
            >
                Copy
            </button>
        </span>
    );
}
