import { Head, Link, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';

/** Phone verification: request a 6-digit code by SMS, then type it in. */
export default function VerifyMobile({ number, status }: { number: string; status?: string }) {
    const sendForm = useForm({});
    const { data, setData, post, processing, errors, reset } = useForm({ code: '' });

    const send: FormEventHandler = (e) => {
        e.preventDefault();
        sendForm.post(route('mobile.verify.send'), { preserveScroll: true });
    };

    const check: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('mobile.verify.check'), { onError: () => reset('code') });
    };

    return (
        <AuthLayout title="Confirm your phone number" description={`We send a 6-digit code by SMS to ${number}.`}>
            <Head title="Confirm your phone number" />

            {status === 'code-sent' && (
                <div className="bg-success-soft text-success mb-2 rounded-md px-3 py-2 text-center text-sm font-medium">
                    A code is on its way. It lasts 10 minutes.
                </div>
            )}

            <form onSubmit={check} className="grid gap-4">
                <div className="grid gap-2">
                    <Label htmlFor="code">Code from the SMS</Label>
                    <Input
                        id="code"
                        inputMode="numeric"
                        autoComplete="one-time-code"
                        maxLength={6}
                        value={data.code}
                        onChange={(e) => setData('code', e.target.value.replace(/\D/g, ''))}
                        placeholder="123456"
                        className="text-center text-lg tracking-[0.4em]"
                    />
                    <InputError message={errors.code} />
                </div>
                <Button type="submit" disabled={processing || data.code.length !== 6}>
                    {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                    Confirm
                </Button>
            </form>

            <form onSubmit={send} className="text-center">
                <Button type="submit" variant="secondary" disabled={sendForm.processing}>
                    {sendForm.processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                    {status === 'code-sent' ? 'Send a new code' : 'Send me a code'}
                </Button>
            </form>

            <div className="text-muted-foreground space-y-2 text-center text-sm">
                <p>
                    Wrong number?{' '}
                    <Link href={route('profile.edit')} className="text-foreground underline underline-offset-4">
                        Change it in your profile
                    </Link>
                </p>
                <TextLink href={route('logout')} method="post" className="mx-auto block">
                    Sign out
                </TextLink>
            </div>
        </AuthLayout>
    );
}
