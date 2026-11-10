import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { type LucideIcon } from 'lucide-react';

interface StatCardProps {
    label: string;
    value: string;
    hint?: string;
    icon?: LucideIcon;
    tone?: 'default' | 'success' | 'warning' | 'info' | 'gold';
}

const tones: Record<NonNullable<StatCardProps['tone']>, string> = {
    default: 'bg-secondary text-secondary-foreground',
    success: 'bg-success-soft text-success',
    warning: 'bg-warning-soft text-warning',
    info: 'bg-info-soft text-info',
    gold: 'bg-accent text-accent-foreground',
};

/** One headline figure with its label, e.g. a wallet balance on a dashboard. */
export function StatCard({ label, value, hint, icon: Icon, tone = 'default' }: StatCardProps) {
    return (
        <Card className="py-0">
            <CardContent className="flex items-start gap-4 p-5">
                {Icon && (
                    <span className={cn('flex size-10 shrink-0 items-center justify-center rounded-lg', tones[tone])}>
                        <Icon className="size-5" aria-hidden />
                    </span>
                )}
                <div className="min-w-0">
                    <p className="text-muted-foreground text-sm">{label}</p>
                    <p className="mt-1 truncate text-2xl font-semibold tabular-nums">{value}</p>
                    {hint && <p className="text-muted-foreground mt-1 text-xs">{hint}</p>}
                </div>
            </CardContent>
        </Card>
    );
}
