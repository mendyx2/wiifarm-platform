import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';

const tabs: [string, string][] = [
    ['admin.reports.financial', 'Financial report'],
    ['admin.reports.transactions', 'Transactions'],
    ['admin.reports.logins', 'Login history'],
];

/** The tabs between the admin reports. */
export function ReportTabs({ current }: { current: string }) {
    return (
        <nav className="flex flex-wrap gap-2" aria-label="Reports">
            {tabs.map(([name, label]) => (
                <Link
                    key={name}
                    href={route(name)}
                    className={cn(
                        'rounded-full border px-3 py-1 text-sm',
                        current === name ? 'bg-primary text-primary-foreground border-primary' : 'hover:bg-muted',
                    )}
                >
                    {label}
                </Link>
            ))}
            <Link href={route('admin.pools')} className="hover:bg-muted rounded-full border px-3 py-1 text-sm">
                Pools by period
            </Link>
        </nav>
    );
}
