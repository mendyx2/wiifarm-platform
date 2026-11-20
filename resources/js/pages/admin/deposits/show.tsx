import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { DepositStatus } from '@/components/wf/deposit-status';
import AdminLayout, { useAdminCan } from '@/layouts/admin-layout';
import { formatKobo } from '@/lib/money';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { type DepositRow } from './index';

export default function AdminDeposit({ deposit: d }: { deposit: DepositRow }) {
    const can = useAdminCan();
    const approve = useForm({});
    const reject = useForm({ reason: '' });
    const errors = usePage().props.errors as Record<string, string>;
    const proof = route('admin.deposits.proof', d.id);
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/admin' },
        { title: 'Deposits', href: '/admin/deposits' },
        { title: `#${d.id}`, href: `/admin/deposits/${d.id}` },
    ];

    const doApprove: FormEventHandler = (e) => {
        e.preventDefault();
        if (confirm(`Add ${formatKobo(d.amount)} to @${d.member}'s main balance?`)) approve.post(route('admin.deposits.approve', d.id));
    };
    const doReject: FormEventHandler = (e) => {
        e.preventDefault();
        reject.post(route('admin.deposits.reject', d.id));
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={`Deposit #${d.id}`} />
            <div className="grid max-w-5xl gap-6 p-4 lg:grid-cols-2">
                <div className="space-y-6">
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <CardTitle className="text-base">Deposit #{d.id}</CardTitle>
                                <DepositStatus status={d.status} />
                            </div>
                            <CardDescription>
                                {can('members.view') ? (
                                    <Link href={route('admin.members.show', d.member_id)} className="underline underline-offset-4">
                                        @{d.member}
                                    </Link>
                                ) : (
                                    `@${d.member}`
                                )}{' '}
                                · sent {d.at}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <dl className="divide-y text-sm">
                                {[
                                    ['Method', d.method],
                                    ['Amount', formatKobo(d.amount)],
                                    ['Payment reference', d.reference],
                                    ...(d.reason ? [['Refused because', d.reason]] : []),
                                    ...(d.decided_at ? [['Decided', d.decided_at]] : []),
                                ].map(([label, value]) => (
                                    <div key={label} className="flex flex-wrap justify-between gap-2 py-2">
                                        <dt className="text-muted-foreground">{label}</dt>
                                        <dd className="text-right font-medium">{value}</dd>
                                    </div>
                                ))}
                            </dl>
                        </CardContent>
                    </Card>

                    {d.status === 'pending' && can('deposits.decide') && (
                        <>
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">Approve</CardTitle>
                                    <CardDescription>Only once you have seen {formatKobo(d.amount)} arrive in Wiifarm's account.</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <form onSubmit={doApprove}>
                                        <InputError message={errors.deposit} />
                                        <Button disabled={approve.processing}>Approve and add to the balance</Button>
                                    </form>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">Refuse</CardTitle>
                                    <CardDescription>Nothing is added. The member sees the reason.</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <form onSubmit={doReject} className="grid gap-2">
                                        <Label htmlFor="reason">Reason</Label>
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
                        </>
                    )}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Proof of payment</CardTitle>
                        <CardDescription>
                            <a href={proof} target="_blank" rel="noreferrer" className="underline underline-offset-4">
                                Open in a new tab
                            </a>
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {d.proof_is_pdf ? (
                            <iframe src={proof} title="Proof of payment" className="h-[32rem] w-full rounded-md border" />
                        ) : (
                            <img src={proof} alt="Proof of payment" className="w-full rounded-md border" />
                        )}
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
