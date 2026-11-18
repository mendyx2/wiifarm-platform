import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { WithdrawalStatus, type WithdrawalRow } from '@/components/wf/withdraw-form';
import AdminLayout, { useAdminCan } from '@/layouts/admin-layout';
import { formatKobo } from '@/lib/money';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

type Detail = WithdrawalRow & { who: string; member_id: number | null; details: { label: string; value: string }[] };

export default function AdminWithdrawal({ withdrawal: w }: { withdrawal: Detail }) {
    const can = useAdminCan();
    const approve = useForm({ reference: '' });
    const reject = useForm({ reason: '' });
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/admin' },
        { title: 'Withdrawals', href: '/admin/withdrawals' },
        { title: `#${w.id}`, href: `/admin/withdrawals/${w.id}` },
    ];

    const doApprove: FormEventHandler = (e) => {
        e.preventDefault();
        approve.post(route('admin.withdrawals.approve', w.id));
    };
    const doReject: FormEventHandler = (e) => {
        e.preventDefault();
        reject.post(route('admin.withdrawals.reject', w.id));
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={`Withdrawal #${w.id}`} />
            <div className="grid max-w-5xl gap-6 p-4 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <div className="flex items-center gap-2">
                            <CardTitle className="text-base">Withdrawal #{w.id}</CardTitle>
                            <WithdrawalStatus status={w.status} />
                        </div>
                        <CardDescription>
                            {w.member_id && can('members.view') ? (
                                <Link href={route('admin.members.show', w.member_id)} className="underline underline-offset-4">
                                    {w.who}
                                </Link>
                            ) : (
                                w.who
                            )}{' '}
                            · requested {w.requested_at}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <dl className="divide-y text-sm">
                            {[
                                ['Paid by', w.method],
                                ...w.details.map((d) => [d.label, d.value]),
                                ['Amount from the wallet', formatKobo(w.amount)],
                                ['Charge (to the cooperative)', formatKobo(w.charge)],
                                ['To send', formatKobo(w.payable)],
                                ...(w.reference ? [['Payment reference', w.reference]] : []),
                                ...(w.reason ? [['Refused because', w.reason]] : []),
                                ...(w.decided_at ? [['Decided', w.decided_at]] : []),
                            ].map(([label, value]) => (
                                <div key={label} className="flex flex-wrap justify-between gap-2 py-2">
                                    <dt className="text-muted-foreground">{label}</dt>
                                    <dd className="text-right font-medium">{value}</dd>
                                </div>
                            ))}
                        </dl>
                    </CardContent>
                </Card>

                {w.status === 'pending' && can('withdrawals.decide') && (
                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Mark as paid</CardTitle>
                                <CardDescription>After you have sent {formatKobo(w.payable)} to the details shown.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <form onSubmit={doApprove} className="grid gap-2">
                                    <Label htmlFor="reference">Payment reference</Label>
                                    <Input
                                        id="reference"
                                        value={approve.data.reference}
                                        onChange={(e) => approve.setData('reference', e.target.value)}
                                    />
                                    <InputError message={approve.errors.reference} />
                                    <Button className="w-fit" disabled={approve.processing || !approve.data.reference.trim()}>
                                        Mark as paid
                                    </Button>
                                </form>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Refuse</CardTitle>
                                <CardDescription>The full {formatKobo(w.amount)} goes back to the wallet.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <form onSubmit={doReject} className="grid gap-2">
                                    <Label htmlFor="reason">Reason (they see it)</Label>
                                    <Textarea
                                        id="reason"
                                        rows={2}
                                        value={reject.data.reason}
                                        onChange={(e) => reject.setData('reason', e.target.value)}
                                    />
                                    <InputError message={reject.errors.reason} />
                                    <Button variant="destructive" className="w-fit" disabled={reject.processing || !reject.data.reason.trim()}>
                                        Refuse
                                    </Button>
                                </form>
                            </CardContent>
                        </Card>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
