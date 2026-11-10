import { Card, CardContent } from '@/components/ui/card';
import { Pagination, type Paginated } from '@/components/wf/pagination';
import { StatementTable } from '@/components/wf/statement-table';
import AppLayout from '@/layouts/app-layout';
import { coreWallets, type StatementRow } from '@/lib/ledger';
import { formatKobo } from '@/lib/money';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

interface WalletProps {
    balances: Record<string, number>;
    labels: Record<string, string>;
    wallet: string;
    entries: Paginated<StatementRow>;
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Wallet', href: '/wallet' }];

function WalletTile({
    kind,
    label,
    balance,
    active,
    large = false,
}: {
    kind: string;
    label: string;
    balance: number;
    active: boolean;
    large?: boolean;
}) {
    return (
        <Link href={route('wallet', kind === 'main' ? {} : { wallet: kind })} preserveScroll aria-current={active ? 'true' : undefined}>
            <Card className={cn('hover:border-primary h-full py-0 transition-colors', active && 'border-primary ring-primary/30 ring-2')}>
                <CardContent className={cn('p-4', large && 'p-5')}>
                    <p className="text-muted-foreground text-sm">{label}</p>
                    <p className={cn('mt-1 font-semibold tabular-nums', large ? 'text-3xl' : 'text-lg')}>{formatKobo(balance)}</p>
                </CardContent>
            </Card>
        </Link>
    );
}

export default function Wallet({ balances, labels, wallet, entries }: WalletProps) {
    const bonus = Object.keys(labels).filter((k) => k.startsWith('bonus.') && (balances[k] !== 0 || k === wallet));

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Wallet" />
            <div className="space-y-6 p-4">
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-[2fr_1fr_1fr_1fr_1fr]">
                    {coreWallets.map((k) => (
                        <WalletTile key={k} kind={k} label={labels[k]} balance={balances[k]} active={wallet === k} large={k === 'main'} />
                    ))}
                </div>

                <section className="space-y-2">
                    <h2 className="font-medium">Bonus wallets</h2>
                    {bonus.length === 0 ? (
                        <p className="text-muted-foreground text-sm">Your bonuses will appear here as you earn them.</p>
                    ) : (
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            {bonus.map((k) => (
                                <WalletTile key={k} kind={k} label={labels[k]} balance={balances[k]} active={wallet === k} />
                            ))}
                        </div>
                    )}
                </section>

                <section className="space-y-2">
                    <h2 className="font-medium">{labels[wallet]}: statement</h2>
                    <StatementTable rows={entries.data} />
                    <Pagination page={entries} />
                </section>
            </div>
        </AppLayout>
    );
}
