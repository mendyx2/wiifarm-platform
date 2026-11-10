import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';

/** The second step of signing in: a code from the authenticator app, or a backup code. */
export default function TwoFactorChallenge() {
    const [backup, setBackup] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({ code: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('two-factor.challenge'), { onError: () => reset('code') });
    };

    return (
        <AuthLayout
            title="One more step"
            description={
                backup
                    ? 'Type one of the backup codes you saved when you turned two-factor on.'
                    : 'Open your authenticator app and type the 6-digit code for Wiifarm.'
            }
        >
            <Head title="Two-factor sign-in" />

            <form onSubmit={submit} className="grid gap-4">
                <div className="grid gap-2">
                    <Label htmlFor="code">{backup ? 'Backup code' : 'Code from your app'}</Label>
                    <Input
                        id="code"
                        autoFocus
                        autoComplete="one-time-code"
                        inputMode={backup ? 'text' : 'numeric'}
                        value={data.code}
                        onChange={(e) => setData('code', e.target.value)}
                        placeholder={backup ? 'ABCDE-FGHIJ' : '123456'}
                        className="text-center text-lg tracking-[0.3em]"
                    />
                    <InputError message={errors.code} />
                </div>
                <Button type="submit" disabled={processing || data.code.trim() === ''}>
                    {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                    Sign in
                </Button>
            </form>

            <div className="text-muted-foreground space-y-2 text-center text-sm">
                <button
                    type="button"
                    className="text-foreground underline underline-offset-4"
                    onClick={() => {
                        setBackup(!backup);
                        reset('code');
                    }}
                >
                    {backup ? 'Use a code from my app instead' : 'I cannot use my app: use a backup code'}
                </button>
                <TextLink href={route('login')} className="mx-auto block">
                    Start again
                </TextLink>
            </div>
        </AuthLayout>
    );
}
