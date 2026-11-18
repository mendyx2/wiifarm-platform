import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';
import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

type Form = { first_name: string; last_name: string; email: string; phone: string; password: string; password_confirmation: string; terms: boolean };

export default function AffiliateRegister() {
    const form = useForm<Form>({ first_name: '', last_name: '', email: '', phone: '', password: '', password_confirmation: '', terms: false });
    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('affiliate.register'), { onFinish: () => form.reset('password', 'password_confirmation') });
    };
    const field = (
        key: 'first_name' | 'last_name' | 'email' | 'phone' | 'password' | 'password_confirmation',
        label: string,
        type = 'text',
        autoComplete?: string,
    ) => (
        <div className="grid gap-1.5">
            <Label htmlFor={key}>{label}</Label>
            <Input id={key} type={type} autoComplete={autoComplete} value={form.data[key]} onChange={(e) => form.setData(key, e.target.value)} />
            <InputError message={form.errors[key]} />
        </div>
    );

    return (
        <AuthLayout
            title="Become a Wiifarm affiliate"
            description="Share Wiifarm products and earn on every sale your link brings. You do not need to be a member."
        >
            <Head title="Affiliate sign-up" />
            <form onSubmit={submit} className="grid gap-4">
                <div className="grid gap-4 sm:grid-cols-2">
                    {field('first_name', 'First name', 'text', 'given-name')}
                    {field('last_name', 'Last name', 'text', 'family-name')}
                </div>
                {field('email', 'Email address', 'email', 'email')}
                {field('phone', 'Phone number', 'tel', 'tel')}
                <div className="grid gap-4 sm:grid-cols-2">
                    {field('password', 'Password', 'password', 'new-password')}
                    {field('password_confirmation', 'Type it again', 'password', 'new-password')}
                </div>
                <div className="flex items-start gap-3">
                    <Checkbox id="terms" checked={form.data.terms} onCheckedChange={(v) => form.setData('terms', v === true)} />
                    <Label htmlFor="terms" className="font-normal">
                        I accept the Wiifarm affiliate terms.
                    </Label>
                </div>
                <InputError message={form.errors.terms} />
                <Button disabled={form.processing}>
                    {form.processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                    Create my affiliate account
                </Button>
                <p className="text-muted-foreground text-center text-sm">
                    Already an affiliate? <TextLink href={route('affiliate.login')}>Sign in</TextLink>
                </p>
            </form>
        </AuthLayout>
    );
}
