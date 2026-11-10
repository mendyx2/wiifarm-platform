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
import { Head, useForm } from '@inertiajs/react';
import { KeyRound, ShieldCheck } from 'lucide-react';
import { FormEventHandler } from 'react';

interface TwoFactorProps {
    on: boolean;
    started: boolean;
    qr: string | null;
    secret: string | null;
    backupCodesLeft: number;
    backupCodes: string[] | null;
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Two-factor sign-in', href: '/settings/two-factor' }];

function BackupCodes({ codes }: { codes: string[] }) {
    return (
        <Alert variant="warning">
            <KeyRound className="size-4" />
            <AlertTitle>Save these backup codes now</AlertTitle>
            <AlertDescription>
                <p className="mb-3">Each one lets you sign in once if you lose your phone. They are shown only this one time.</p>
                <ul className="grid grid-cols-2 gap-2 font-mono text-sm">
                    {codes.map((c) => (
                        <li key={c} className="bg-background rounded border px-2 py-1 text-center">
                            {c}
                        </li>
                    ))}
                </ul>
            </AlertDescription>
        </Alert>
    );
}

export default function TwoFactor({ on, started, qr, secret, backupCodesLeft, backupCodes }: TwoFactorProps) {
    const startForm = useForm({ password: '' });
    const confirmForm = useForm({ code: '' });
    const regenerateForm = useForm({ password: '' });
    const offForm = useForm({ password: '' });

    const start: FormEventHandler = (e) => {
        e.preventDefault();
        startForm.post(route('two-factor.start'), { onFinish: () => startForm.reset('password') });
    };
    const confirm: FormEventHandler = (e) => {
        e.preventDefault();
        confirmForm.post(route('two-factor.confirm'), { onError: () => confirmForm.reset('code') });
    };
    const regenerate: FormEventHandler = (e) => {
        e.preventDefault();
        regenerateForm.post(route('two-factor.regenerate'), { onFinish: () => regenerateForm.reset('password') });
    };
    const turnOff: FormEventHandler = (e) => {
        e.preventDefault();
        offForm.delete(route('two-factor.destroy'), { onFinish: () => offForm.reset('password') });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Two-factor sign-in" />

            <SettingsLayout>
                <div className="space-y-6">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <HeadingSmall
                            title="Two-factor sign-in"
                            description="Besides your password, ask for a code from an authenticator app on your phone. It protects your account and your money if your password is ever stolen."
                        />
                        <Badge variant={on ? 'success' : 'outline'}>{on ? 'On' : 'Off'}</Badge>
                    </div>

                    {backupCodes && <BackupCodes codes={backupCodes} />}

                    {!on && !started && (
                        <form onSubmit={start} className="grid max-w-xs gap-2">
                            <Label htmlFor="start-password">To turn it on, type your password</Label>
                            <Input
                                id="start-password"
                                type="password"
                                autoComplete="current-password"
                                value={startForm.data.password}
                                onChange={(e) => startForm.setData('password', e.target.value)}
                            />
                            <InputError message={startForm.errors.password} />
                            <Button className="w-fit" disabled={startForm.processing || startForm.data.password === ''}>
                                Turn on two-factor sign-in
                            </Button>
                        </form>
                    )}

                    {!on && started && (
                        <div className="space-y-4">
                            <ol className="list-decimal space-y-1 pl-5 text-sm">
                                <li>Install an authenticator app (Google Authenticator, Microsoft Authenticator or Authy).</li>
                                <li>In the app, add an account and scan this code.</li>
                                <li>Type the 6-digit code the app shows.</li>
                            </ol>
                            {qr && <div className="w-fit rounded-lg border bg-white p-3" dangerouslySetInnerHTML={{ __html: qr }} />}
                            <p className="text-muted-foreground text-xs">
                                Cannot scan? Enter this key in the app instead: <span className="text-foreground font-mono break-all">{secret}</span>
                            </p>
                            <form onSubmit={confirm} className="grid max-w-xs gap-2">
                                <Label htmlFor="code">Code from the app</Label>
                                <Input
                                    id="code"
                                    inputMode="numeric"
                                    autoComplete="one-time-code"
                                    maxLength={6}
                                    value={confirmForm.data.code}
                                    onChange={(e) => confirmForm.setData('code', e.target.value.replace(/\D/g, ''))}
                                />
                                <InputError message={confirmForm.errors.code} />
                                <Button className="w-fit" disabled={confirmForm.processing || confirmForm.data.code.length !== 6}>
                                    Confirm and turn on
                                </Button>
                            </form>
                        </div>
                    )}

                    {on && (
                        <div className="space-y-6">
                            <Alert variant="success">
                                <ShieldCheck className="size-4" />
                                <AlertTitle>Your account asks for a code at every sign-in</AlertTitle>
                                <AlertDescription>
                                    {backupCodesLeft} backup code{backupCodesLeft === 1 ? '' : 's'} left.
                                </AlertDescription>
                            </Alert>

                            <form onSubmit={regenerate} className="grid max-w-xs gap-2">
                                <Label htmlFor="regen-password">New backup codes (the old ones stop working)</Label>
                                <Input
                                    id="regen-password"
                                    type="password"
                                    placeholder="Your password"
                                    autoComplete="current-password"
                                    value={regenerateForm.data.password}
                                    onChange={(e) => regenerateForm.setData('password', e.target.value)}
                                />
                                <InputError message={regenerateForm.errors.password} />
                                <Button
                                    variant="secondary"
                                    className="w-fit"
                                    disabled={regenerateForm.processing || regenerateForm.data.password === ''}
                                >
                                    Make new backup codes
                                </Button>
                            </form>

                            <form onSubmit={turnOff} className="grid max-w-xs gap-2 border-t pt-6">
                                <Label htmlFor="password">To turn it off, type your password</Label>
                                <Input
                                    id="password"
                                    type="password"
                                    autoComplete="current-password"
                                    value={offForm.data.password}
                                    onChange={(e) => offForm.setData('password', e.target.value)}
                                />
                                <InputError message={offForm.errors.password} />
                                <Button variant="destructive" className="w-fit" disabled={offForm.processing || offForm.data.password === ''}>
                                    Turn off two-factor sign-in
                                </Button>
                            </form>
                        </div>
                    )}
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
