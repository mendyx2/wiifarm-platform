import { type Method, type WalletOption, WithdrawForm, WithdrawalHistory, type WithdrawalRow } from '@/components/wf/withdraw-form';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Wallet', href: '/wallet' },
    { title: 'Withdraw', href: '/withdraw' },
];

export default function Withdraw({
    wallets,
    methods,
    blocked,
    withdrawals,
}: {
    wallets: WalletOption[];
    methods: Method[];
    blocked: string | null;
    withdrawals: WithdrawalRow[];
}) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Withdraw" />
            <div className="max-w-4xl space-y-6 p-4">
                <p className="text-muted-foreground text-sm">
                    Bonuses are withdrawn from your main balance: move them there first on the Wallet page.
                </p>
                <WithdrawForm wallets={wallets} methods={methods} action={route('withdraw.store')} blocked={blocked} />
                <section className="space-y-2">
                    <h2 className="font-medium">Your withdrawals</h2>
                    <WithdrawalHistory rows={withdrawals} />
                </section>
            </div>
        </AppLayout>
    );
}
