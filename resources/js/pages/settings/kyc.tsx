import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/react';
import { CheckCircle2, Clock, TriangleAlert } from 'lucide-react';
import { FormEventHandler } from 'react';

type Field = { key: string; label: string; type: 'text' | 'select' | 'file'; required?: boolean; options?: string[]; hint?: string };

type Latest = {
    id: number;
    status: 'pending' | 'approved' | 'rejected';
    submitted_at: string;
    rejection_reason: string | null;
    answers: { label: string; type: string; value: string | null; url: string | null }[];
};

interface KycProps {
    status: 'unverified' | 'pending' | 'verified';
    fields: Field[];
    latest: Latest | null;
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Identity (KYC)', href: '/settings/kyc' }];

const badge = { unverified: ['Not verified', 'outline'], pending: ['Under review', 'warning'], verified: ['Verified', 'success'] } as const;

function Answers({ latest }: { latest: Latest }) {
    return (
        <dl className="divide-y rounded-lg border text-sm">
            {latest.answers.map((a) => (
                <div key={a.label} className="flex flex-wrap justify-between gap-2 px-4 py-3">
                    <dt className="text-muted-foreground">{a.label}</dt>
                    <dd className="font-medium">
                        {a.url ? (
                            <a href={a.url} target="_blank" rel="noreferrer" className="text-primary underline underline-offset-4">
                                View file
                            </a>
                        ) : (
                            (a.value ?? '—')
                        )}
                    </dd>
                </div>
            ))}
        </dl>
    );
}

export default function Kyc({ status, fields, latest }: KycProps) {
    const { errors: pageErrors } = usePage().props as { errors: Record<string, string> };
    const { data, setData, post, processing, errors, progress } = useForm<{ answers: Record<string, string | File | null> }>({
        answers: Object.fromEntries(fields.map((f) => [f.key, f.type === 'select' ? '' : f.type === 'file' ? null : ''])),
    });

    const setAnswer = (key: string, value: string | File | null) => setData('answers', { ...data.answers, [key]: value });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('kyc.store'), { forceFormData: true, preserveScroll: true });
    };

    const [label, variant] = badge[status];
    const canSubmit = status === 'unverified';
    const fieldError = (key: string) => (errors as Record<string, string>)[`answers.${key}`];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Identity (KYC)" />

            <SettingsLayout>
                <div className="space-y-6">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <HeadingSmall
                            title="Identity check (KYC)"
                            description="Wiifarm confirms who you are before some features open, such as withdrawals."
                        />
                        <Badge variant={variant}>{label}</Badge>
                    </div>

                    {status === 'verified' && (
                        <Alert variant="success">
                            <CheckCircle2 className="size-4" />
                            <AlertTitle>Your identity is verified</AlertTitle>
                            <AlertDescription>Nothing more is needed.</AlertDescription>
                        </Alert>
                    )}

                    {status === 'pending' && (
                        <Alert variant="info">
                            <Clock className="size-4" />
                            <AlertTitle>Your documents are being reviewed</AlertTitle>
                            <AlertDescription>An admin will check them shortly. You will see the result here.</AlertDescription>
                        </Alert>
                    )}

                    {canSubmit && latest?.status === 'rejected' && (
                        <Alert variant="warning">
                            <TriangleAlert className="size-4" />
                            <AlertTitle>Your last submission was not accepted</AlertTitle>
                            <AlertDescription>{latest.rejection_reason} Please correct it and submit again.</AlertDescription>
                        </Alert>
                    )}

                    {latest && !canSubmit && <Answers latest={latest} />}

                    {canSubmit && (
                        <form onSubmit={submit} className="space-y-5" encType="multipart/form-data">
                            {fields.map((f) => (
                                <div key={f.key} className="grid gap-2">
                                    <Label htmlFor={`kyc-${f.key}`}>
                                        {f.label}
                                        {!f.required && <span className="text-muted-foreground font-normal"> (optional)</span>}
                                    </Label>
                                    {f.type === 'select' ? (
                                        <select
                                            id={`kyc-${f.key}`}
                                            className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                                            value={(data.answers[f.key] as string) ?? ''}
                                            onChange={(e) => setAnswer(f.key, e.target.value)}
                                        >
                                            <option value="">Choose…</option>
                                            {f.options?.map((o) => (
                                                <option key={o} value={o}>
                                                    {o}
                                                </option>
                                            ))}
                                        </select>
                                    ) : f.type === 'file' ? (
                                        <Input
                                            id={`kyc-${f.key}`}
                                            type="file"
                                            accept="image/jpeg,image/png,application/pdf"
                                            onChange={(e) => setAnswer(f.key, e.target.files?.[0] ?? null)}
                                        />
                                    ) : (
                                        <Input
                                            id={`kyc-${f.key}`}
                                            value={(data.answers[f.key] as string) ?? ''}
                                            onChange={(e) => setAnswer(f.key, e.target.value)}
                                        />
                                    )}
                                    {f.type === 'file' && !fieldError(f.key) && (
                                        <p className="text-muted-foreground text-xs">A photo (JPG or PNG) or a PDF, up to 5 MB.</p>
                                    )}
                                    {f.hint && !fieldError(f.key) && <p className="text-muted-foreground text-xs">{f.hint}</p>}
                                    <InputError message={fieldError(f.key)} />
                                </div>
                            ))}

                            <InputError message={pageErrors.kyc} />

                            {progress && <p className="text-muted-foreground text-sm">Uploading… {progress.percentage}%</p>}

                            <Button disabled={processing}>Submit for review</Button>
                        </form>
                    )}
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
