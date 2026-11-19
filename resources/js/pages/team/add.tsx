import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { FormField, selectClass } from '@/components/wf/form-field';
import AppLayout from '@/layouts/app-layout';
import { countries } from '@/lib/countries';
import { formatKobo } from '@/lib/money';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

type PackageOption = { id: number; name: string; price: number; pv: number };

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'My team', href: '/team' },
    { title: 'Add distributor', href: '/team/add' },
];

export default function AddDistributor({
    visa,
    canKeyIn,
    packages,
    defaults,
}: {
    visa: number;
    canKeyIn: boolean;
    packages: PackageOption[];
    defaults: { dial_code: string; country: string; sponsor: string };
}) {
    const [formId] = useState(() => crypto.randomUUID());
    const form = useForm({
        first_name: '',
        last_name: '',
        username: '',
        email: '',
        dial_code: defaults.dial_code,
        mobile: '',
        country: defaults.country,
        state: '',
        city: '',
        address: '',
        sponsor: defaults.sponsor,
        parent: '',
        leg: '' as '' | 'L' | 'R',
        package_id: packages[0]?.id ?? 0,
        password: '',
        password_confirmation: '',
        terms: false as boolean,
        form_id: formId,
    });
    const { data, setData, errors, processing } = form;
    const chosen = packages.find((p) => p.id === data.package_id);
    const short = chosen ? chosen.price > visa : false;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('team.add.store'), { onFinish: () => form.reset('password', 'password_confirmation') });
    };
    const pickCountry = (code: string) => {
        const match = countries.find(([c]) => c === code);
        setData((d) => ({ ...d, country: code, dial_code: match ? match[2] : d.dial_code }));
    };
    const text = (key: 'first_name' | 'last_name' | 'username' | 'email' | 'state' | 'city' | 'address' | 'sponsor' | 'parent') => ({
        id: `k-${key}`,
        value: data[key],
        onChange: (e: React.ChangeEvent<HTMLInputElement>) => setData(key, e.target.value),
        disabled: processing,
    });

    if (!canKeyIn) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Add distributor" />
                <p className="p-4 text-sm">
                    You can add distributors once your own package is paid. <Link href={route('package')}>Choose your package</Link>.
                </p>
            </AppLayout>
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Add distributor" />
            <form onSubmit={submit} className="max-w-3xl space-y-6 p-4">
                <p className="text-sm">
                    Register someone and pay their package from your VISA wallet ({formatKobo(visa)} available). They become a member at once, and you
                    earn the key-in bonus.
                </p>

                <section className="bg-card shadow-soft grid gap-5 rounded-xl border p-4">
                    <h2 className="font-medium">Their details</h2>
                    <div className="grid gap-5 sm:grid-cols-2">
                        <FormField id="k-first_name" label="First name" error={errors.first_name}>
                            <Input required {...text('first_name')} />
                        </FormField>
                        <FormField id="k-last_name" label="Last name" error={errors.last_name}>
                            <Input required {...text('last_name')} />
                        </FormField>
                        <FormField id="k-username" label="Their username" error={errors.username} hint="6 to 40 letters, numbers or underscores.">
                            <Input required autoComplete="off" {...text('username')} />
                        </FormField>
                        <FormField id="k-email" label="Their email address" error={errors.email}>
                            <Input required type="email" autoComplete="off" {...text('email')} />
                        </FormField>
                        <FormField id="k-country" label="Country" error={errors.country}>
                            <select id="k-country" className={selectClass} value={data.country} onChange={(e) => pickCountry(e.target.value)}>
                                {countries.map(([code, name]) => (
                                    <option key={code} value={code}>
                                        {name}
                                    </option>
                                ))}
                            </select>
                        </FormField>
                        <FormField
                            id="k-mobile"
                            label="Mobile number"
                            error={errors.mobile ?? errors.dial_code}
                            hint="Digits only, without the country code."
                        >
                            <div className="flex gap-2">
                                <span className="border-input bg-muted text-muted-foreground flex h-9 items-center rounded-md border px-3 text-sm">
                                    +{data.dial_code}
                                </span>
                                <Input
                                    id="k-mobile"
                                    required
                                    inputMode="numeric"
                                    value={data.mobile}
                                    onChange={(e) => setData('mobile', e.target.value.replace(/\D/g, ''))}
                                />
                            </div>
                        </FormField>
                        <FormField id="k-state" label="State" error={errors.state}>
                            <Input required {...text('state')} />
                        </FormField>
                        <FormField id="k-city" label="City" error={errors.city}>
                            <Input required {...text('city')} />
                        </FormField>
                    </div>
                    <FormField id="k-address" label="Address" error={errors.address}>
                        <Input required {...text('address')} />
                    </FormField>
                    <div className="grid gap-5 sm:grid-cols-2">
                        <FormField id="k-password" label="Their password" error={errors.password} hint="Give it to them; they can change it later.">
                            <Input
                                id="k-password"
                                type="password"
                                required
                                autoComplete="new-password"
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                            />
                        </FormField>
                        <FormField id="k-password_confirmation" label="Type it again" error={errors.password_confirmation}>
                            <Input
                                id="k-password_confirmation"
                                type="password"
                                required
                                autoComplete="new-password"
                                value={data.password_confirmation}
                                onChange={(e) => setData('password_confirmation', e.target.value)}
                            />
                        </FormField>
                    </div>
                </section>

                <section className="bg-card shadow-soft grid gap-5 rounded-xl border p-4">
                    <h2 className="font-medium">Their place in the team</h2>
                    <FormField id="k-sponsor" label="Sponsor's username" error={errors.sponsor} hint="You, unless someone else invited them.">
                        <Input required {...text('sponsor')} />
                    </FormField>
                    <div className="grid gap-5 sm:grid-cols-2">
                        <FormField id="k-parent" label="Place under (optional)" error={errors.parent} hint="Must be in the sponsor's team.">
                            <Input {...text('parent')} />
                        </FormField>
                        <FormField id="k-leg" label="On their" error={errors.leg}>
                            <select
                                id="k-leg"
                                className={selectClass}
                                value={data.leg}
                                onChange={(e) => setData('leg', e.target.value as '' | 'L' | 'R')}
                            >
                                <option value="">No preference</option>
                                <option value="L">Left</option>
                                <option value="R">Right</option>
                            </select>
                        </FormField>
                    </div>
                </section>

                <section className="bg-card shadow-soft grid gap-5 rounded-xl border p-4">
                    <h2 className="font-medium">Their package</h2>
                    <FormField id="k-package" label="Package" error={errors.package_id}>
                        <select
                            id="k-package"
                            className={selectClass}
                            value={data.package_id}
                            onChange={(e) => setData('package_id', Number(e.target.value))}
                        >
                            {packages.map((p) => (
                                <option key={p.id} value={p.id}>
                                    {p.name}: {formatKobo(p.price)} ({p.pv} PV)
                                </option>
                            ))}
                        </select>
                    </FormField>
                    {short && <p className="text-destructive text-sm">Your VISA wallet has {formatKobo(visa)}: not enough for this package.</p>}
                    <div className="grid gap-2">
                        <div className="flex items-start gap-3">
                            <Checkbox id="k-terms" checked={data.terms} onCheckedChange={(v) => setData('terms', v === true)} />
                            <Label htmlFor="k-terms" className="leading-snug font-normal">
                                The person I am registering accepts the Wiifarm member terms.
                            </Label>
                        </div>
                        <InputError message={errors.terms} />
                    </div>
                    <Button disabled={processing || short || !chosen}>
                        {chosen ? `Register and pay ${formatKobo(chosen.price)} from my VISA wallet` : 'Choose a package'}
                    </Button>
                </section>
            </form>
        </AppLayout>
    );
}
