import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';
import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

export default function AffiliateLogin() {
    const form = useForm({ email: '', password: '' });
    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('affiliate.login'), { onFinish: () => form.reset('password') });
    };

    return (
        <AuthLayout title="Affiliate sign-in" description="Sign in with the email address you registered as an affiliate.">
            <Head title="Affiliate sign-in" />
            <form onSubmit={submit} className="grid gap-4">
                <div className="grid gap-1.5">
                    <Label htmlFor="email">Email address</Label>
                    <Input
                        id="email"
                        type="email"
                        autoComplete="email"
                        autoFocus
                        value={form.data.email}
                        onChange={(e) => form.setData('email', e.target.value)}
                    />
                    <InputError message={form.errors.email} />
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor="password">Password</Label>
                    <Input
                        id="password"
                        type="password"
                        autoComplete="current-password"
                        value={form.data.password}
                        onChange={(e) => form.setData('password', e.target.value)}
                    />
                </div>
                <Button disabled={form.processing}>
                    {form.processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                    Sign in
                </Button>
                <p className="text-muted-foreground text-center text-sm">
                    Not an affiliate yet? <TextLink href={route('affiliate.register')}>Sign up</TextLink>
                </p>
            </form>
        </AuthLayout>
    );
}
