import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler, type ReactNode } from 'react';

import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';
import { countries } from '@/lib/countries';

type RegisterForm = {
    first_name: string;
    last_name: string;
    username: string;
    email: string;
    dial_code: string;
    mobile: string;
    country: string;
    sponsor: string;
    parent: string;
    leg: '' | 'L' | 'R';
    password: string;
    password_confirmation: string;
    terms: boolean;
};

interface RegisterProps {
    defaults: { dial_code: string; country: string };
    sponsor: string | null;
}

function Field({ id, label, error, hint, children }: { id: string; label: string; error?: string; hint?: string; children: ReactNode }) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            {children}
            {hint && !error && <p className="text-muted-foreground text-xs">{hint}</p>}
            <InputError message={error} />
        </div>
    );
}

export default function Register({ defaults, sponsor }: RegisterProps) {
    const { data, setData, post, processing, errors, reset } = useForm<RegisterForm>({
        first_name: '',
        last_name: '',
        username: '',
        email: '',
        dial_code: defaults.dial_code,
        mobile: '',
        country: defaults.country,
        sponsor: sponsor ?? '',
        parent: '',
        leg: '',
        password: '',
        password_confirmation: '',
        terms: false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('register'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    const pickCountry = (code: string) => {
        const match = countries.find(([c]) => c === code);
        setData((d) => ({ ...d, country: code, dial_code: match ? match[2] : d.dial_code }));
    };

    return (
        <AuthLayout title="Join Wiifarm" description="Create your account. You become a member when your package is paid.">
            <Head title="Join Wiifarm" />
            <form className="flex flex-col gap-6" onSubmit={submit}>
                <div className="grid gap-5">
                    <div className="grid gap-5 sm:grid-cols-2">
                        <Field id="first_name" label="First name" error={errors.first_name}>
                            <Input
                                id="first_name"
                                required
                                autoFocus
                                autoComplete="given-name"
                                value={data.first_name}
                                onChange={(e) => setData('first_name', e.target.value)}
                                disabled={processing}
                            />
                        </Field>
                        <Field id="last_name" label="Last name" error={errors.last_name}>
                            <Input
                                id="last_name"
                                required
                                autoComplete="family-name"
                                value={data.last_name}
                                onChange={(e) => setData('last_name', e.target.value)}
                                disabled={processing}
                            />
                        </Field>
                    </div>

                    <Field
                        id="username"
                        label="Username"
                        error={errors.username}
                        hint="6 to 40 letters, numbers or underscores. It cannot be changed later."
                    >
                        <Input
                            id="username"
                            required
                            autoComplete="username"
                            value={data.username}
                            onChange={(e) => setData('username', e.target.value)}
                            disabled={processing}
                        />
                    </Field>

                    <Field id="email" label="Email address" error={errors.email}>
                        <Input
                            id="email"
                            type="email"
                            required
                            autoComplete="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            disabled={processing}
                            placeholder="you@example.com"
                        />
                    </Field>

                    <Field id="country" label="Country" error={errors.country}>
                        <select
                            id="country"
                            className="border-input bg-background focus-visible:ring-ring h-9 rounded-md border px-3 text-sm focus-visible:ring-2 focus-visible:outline-none"
                            value={data.country}
                            onChange={(e) => pickCountry(e.target.value)}
                            disabled={processing}
                        >
                            {countries.map(([code, name]) => (
                                <option key={code} value={code}>
                                    {name}
                                </option>
                            ))}
                        </select>
                    </Field>

                    <Field id="mobile" label="Mobile number" error={errors.mobile ?? errors.dial_code} hint="Digits only, without the country code.">
                        <div className="flex gap-2">
                            <span className="border-input bg-muted text-muted-foreground flex h-9 items-center rounded-md border px-3 text-sm">
                                +{data.dial_code}
                            </span>
                            <Input
                                id="mobile"
                                required
                                inputMode="numeric"
                                autoComplete="tel-national"
                                value={data.mobile}
                                onChange={(e) => setData('mobile', e.target.value.replace(/\D/g, ''))}
                                disabled={processing}
                                placeholder="8012345678"
                            />
                        </div>
                    </Field>

                    <Field
                        id="sponsor"
                        label="Sponsor's username (optional)"
                        error={errors.sponsor}
                        hint="The member who invited you. Leave it blank if nobody did."
                    >
                        <Input id="sponsor" value={data.sponsor} onChange={(e) => setData('sponsor', e.target.value)} disabled={processing} />
                    </Field>

                    <details className="rounded-md border px-3 py-2 text-sm" open={!!(data.parent || data.leg || errors.parent || errors.leg)}>
                        <summary className="cursor-pointer font-medium">Choose your place in the team (optional)</summary>
                        <p className="text-muted-foreground mt-2 text-xs">
                            Leave this alone unless your sponsor told you where to go. Without a choice you get the first free place below your
                            sponsor.
                        </p>
                        <div className="mt-3 grid gap-5 sm:grid-cols-2">
                            <Field id="parent" label="Place me under (username)" error={errors.parent} hint="Must be in your sponsor's team.">
                                <Input id="parent" value={data.parent} onChange={(e) => setData('parent', e.target.value)} disabled={processing} />
                            </Field>
                            <Field id="leg" label="On their" error={errors.leg}>
                                <select
                                    id="leg"
                                    className="border-input bg-background h-10 rounded-md border px-3 text-sm"
                                    value={data.leg}
                                    onChange={(e) => setData('leg', e.target.value as RegisterForm['leg'])}
                                    disabled={processing}
                                >
                                    <option value="">No preference</option>
                                    <option value="L">Left</option>
                                    <option value="R">Right</option>
                                </select>
                            </Field>
                        </div>
                    </details>

                    <div className="grid gap-5 sm:grid-cols-2">
                        <Field id="password" label="Password" error={errors.password}>
                            <Input
                                id="password"
                                type="password"
                                required
                                autoComplete="new-password"
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                disabled={processing}
                            />
                        </Field>
                        <Field id="password_confirmation" label="Type it again" error={errors.password_confirmation}>
                            <Input
                                id="password_confirmation"
                                type="password"
                                required
                                autoComplete="new-password"
                                value={data.password_confirmation}
                                onChange={(e) => setData('password_confirmation', e.target.value)}
                                disabled={processing}
                            />
                        </Field>
                    </div>

                    <div className="grid gap-2">
                        <div className="flex items-start gap-3">
                            <Checkbox id="terms" checked={data.terms} onCheckedChange={(v) => setData('terms', v === true)} disabled={processing} />
                            <Label htmlFor="terms" className="leading-snug font-normal">
                                I accept the Wiifarm member terms.
                            </Label>
                        </div>
                        <InputError message={errors.terms} />
                    </div>

                    <Button type="submit" className="mt-1 w-full" disabled={processing}>
                        {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                        Create my account
                    </Button>
                </div>

                <div className="text-muted-foreground text-center text-sm">
                    Already a member? <TextLink href={route('login')}>Sign in</TextLink>
                </div>
            </form>
        </AuthLayout>
    );
}
