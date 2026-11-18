import { Button } from '@/components/ui/button';
import { type Method, type WalletOption, WithdrawForm, WithdrawalHistory, type WithdrawalRow } from '@/components/wf/withdraw-form';
import AffiliateLayout from '@/layouts/affiliate-layout';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

export default function AffiliateWithdraw({
    wallets,
    methods,
    withdrawals,
}: {
    wallets: WalletOption[];
    methods: Method[];
    withdrawals: WithdrawalRow[];
}) {
    return (
        <AffiliateLayout>
            <Head title="Withdraw" />
            <div className="max-w-4xl space-y-6 p-4">
                <Button asChild variant="ghost" size="sm">
                    <Link href={route('affiliate.dashboard')}>
                        <ArrowLeft className="size-4" />
                        Back to the dashboard
                    </Link>
                </Button>
                <WithdrawForm wallets={wallets} methods={methods} action={route('affiliate.withdraw.store')} />
                <section className="space-y-2">
                    <h2 className="font-medium">Your withdrawals</h2>
                    <WithdrawalHistory rows={withdrawals} />
                </section>
            </div>
        </AffiliateLayout>
    );
}
