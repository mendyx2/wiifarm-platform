import { KycBadge } from '@/components/admin/badges';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AdminLayout, { useAdminCan } from '@/layouts/admin-layout';
import { countryName } from '@/lib/countries';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { CheckCircle2, ExternalLink, XCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

interface Submission {
    id: number;
    status: string;
    submitted_at: string;
    reviewed_at: string | null;
    reviewed_by: string | null;
    rejection_reason: string | null;
    member: { id: number; username: string; name: string; email: string; country: string };
    answers: { label: string; type: string; value: string | null; url: string | null; isImage: boolean }[];
}

interface ShowProps {
    submission: Submission;
    earlier: { id: number; status: string; submitted_at: string; rejection_reason: string | null }[];
}

function Decision({ submission }: { submission: Submission }) {
    const approve = useForm({});
    const reject = useForm({ reason: '' });
    const { errors } = usePage<{ errors: Record<string, string> }>().props;

    const doReject: FormEventHandler = (e) => {
        e.preventDefault();
        reject.post(route('admin.kyc.reject', submission.id), { preserveScroll: true });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">Your decision</CardTitle>
                <CardDescription>Check the name and photos match the member's details. The decision is saved in the audit log.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
                <InputError message={errors.kyc} />
                <Button
                    onClick={() => approve.post(route('admin.kyc.approve', submission.id), { preserveScroll: true })}
                    disabled={approve.processing || reject.processing}
                >
                    <CheckCircle2 className="size-4" />
                    Approve: identity confirmed
                </Button>
                <form onSubmit={doReject} className="grid gap-2 border-t pt-6">
                    <Label htmlFor="reason">Or reject, with a reason the member will see</Label>
                    <Textarea
                        id="reason"
                        rows={2}
                        placeholder="e.g. The ID photo is blurred. Please upload a clear photo."
                        value={reject.data.reason}
                        onChange={(e) => reject.setData('reason', e.target.value)}
                    />
                    <InputError message={reject.errors.reason} />
                    <Button
                        variant="destructive"
                        className="w-fit"
                        disabled={reject.processing || approve.processing || reject.data.reason.trim() === ''}
                    >
                        <XCircle className="size-4" />
                        Reject
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}

export default function KycShow({ submission, earlier }: ShowProps) {
    const can = useAdminCan();
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/admin' },
        { title: 'KYC review', href: '/admin/kyc' },
        { title: `@${submission.member.username}`, href: `/admin/kyc/${submission.id}` },
    ];

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={`KYC: @${submission.member.username}`} />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">{submission.member.name}</h1>
                        <p className="text-muted-foreground text-sm">
                            {can('members.view') ? (
                                <Link href={route('admin.members.show', submission.member.id)} className="underline underline-offset-4">
                                    @{submission.member.username}
                                </Link>
                            ) : (
                                `@${submission.member.username}`
                            )}{' '}
                            · {submission.member.email} · {countryName(submission.member.country)} · submitted {submission.submitted_at}
                        </p>
                    </div>
                    <KycBadge status={submission.status} short />
                </div>

                {submission.status !== 'pending' && (
                    <Alert variant={submission.status === 'approved' ? 'success' : 'warning'}>
                        <AlertTitle>
                            {submission.status === 'approved' ? 'Approved' : 'Rejected'} by @{submission.reviewed_by ?? 'unknown'} on{' '}
                            {submission.reviewed_at}
                        </AlertTitle>
                        {submission.rejection_reason && <AlertDescription>{submission.rejection_reason}</AlertDescription>}
                    </Alert>
                )}

                <div className="grid gap-6 lg:grid-cols-[2fr_1fr]">
                    <div className="space-y-4">
                        {submission.answers.map((a) => (
                            <Card key={a.label}>
                                <CardContent className="space-y-2 p-4">
                                    <p className="text-muted-foreground text-sm">{a.label}</p>
                                    {a.type !== 'file' && <p className="font-medium">{a.value ?? '—'}</p>}
                                    {a.url && a.isImage && (
                                        <a href={a.url} target="_blank" rel="noreferrer">
                                            <img src={a.url} alt={a.label} className="max-h-96 rounded-md border object-contain" />
                                        </a>
                                    )}
                                    {a.url && (
                                        <a
                                            href={a.url}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="text-primary inline-flex items-center gap-1 text-sm underline underline-offset-4"
                                        >
                                            Open the file <ExternalLink className="size-3.5" />
                                        </a>
                                    )}
                                    {a.type === 'file' && !a.url && <p className="text-muted-foreground text-sm">No file</p>}
                                </CardContent>
                            </Card>
                        ))}
                    </div>

                    <div className="space-y-6">
                        {submission.status === 'pending' && can('kyc.review') && <Decision submission={submission} />}

                        {earlier.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">Earlier submissions</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-3 text-sm">
                                    {earlier.map((e) => (
                                        <div key={e.id} className="space-y-1">
                                            <Link
                                                href={route('admin.kyc.show', e.id)}
                                                className="flex items-center gap-2 underline underline-offset-4"
                                            >
                                                {e.submitted_at} <KycBadge status={e.status} short />
                                            </Link>
                                            {e.rejection_reason && <p className="text-muted-foreground">{e.rejection_reason}</p>}
                                        </div>
                                    ))}
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
