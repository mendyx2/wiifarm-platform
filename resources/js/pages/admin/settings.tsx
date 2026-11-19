import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { FormField, selectClass } from '@/components/wf/form-field';
import AdminLayout, { useAdminCan } from '@/layouts/admin-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { Lock } from 'lucide-react';
import { FormEventHandler } from 'react';

type Switch = { key: string; help: string; value: boolean };

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin' },
    { title: 'Settings', href: '/admin/settings' },
];

const titles: Record<string, string> = {
    'verification.email_required': 'Email confirmation required',
    'verification.mobile_required': 'Phone confirmation required',
    'withdrawals.kyc_required': 'Identity check (KYC) before withdrawing',
    'matching.autoship_hold': 'Autoship hold on matching bonuses',
};

/** Form field names cannot hold dots, so "verification.email_required" travels as "verification_email_required". */
const field = (key: string) => key.replace(/\./g, '_');

function LeadershipTeam({ value, editable }: { value: { team: string; levels: number }; editable: boolean }) {
    const form = useForm({ team: value.team, levels: value.levels });
    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.put(route('admin.settings.leadership'), { preserveScroll: true, onSuccess: () => form.setDefaults() });
    };
    const unit = form.data.team === 'sponsor' ? 'generations' : 'levels';

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">Leadership bonus: who is the team</CardTitle>
                <CardDescription>
                    A leader earns 5% of what their team earns from matching each month. The board has not yet said who the team is (open decision 3),
                    so it is set here. A change applies from the next monthly run, and each run records the rule it used.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form onSubmit={submit} className="grid gap-4 sm:grid-cols-[2fr_1fr_auto] sm:items-end">
                    <FormField id="l-team" label="Count the team through" error={form.errors.team}>
                        <select
                            id="l-team"
                            className={selectClass}
                            disabled={!editable}
                            value={form.data.team}
                            onChange={(e) => form.setData('team', e.target.value)}
                        >
                            <option value="tree">The binary tree: everyone below the leader</option>
                            <option value="sponsor">The sponsor chain: everyone the leader brought in, and theirs</option>
                        </select>
                    </FormField>
                    <FormField id="l-levels" label={`How many ${unit}`} error={form.errors.levels} hint="0 means no limit.">
                        <Input
                            id="l-levels"
                            type="number"
                            min={0}
                            max={100}
                            disabled={!editable}
                            value={form.data.levels}
                            onChange={(e) => form.setData('levels', Number(e.target.value))}
                        />
                    </FormField>
                    {editable && <Button disabled={form.processing || !form.isDirty}>Save</Button>}
                </form>
            </CardContent>
        </Card>
    );
}

function MonthlyPurchase({ value, editable }: { value: number; editable: boolean }) {
    const form = useForm({ monthly_pv: String(value) });
    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.put(route('admin.settings.activity'), { preserveScroll: true, onSuccess: () => form.setDefaults() });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">Monthly purchase requirement</CardTitle>
                <CardDescription>
                    The PV of products a member buys each calendar month to stay active (Revised Baseline Rules: 10.5 PV, ₦10,500). It decides the
                    autoship hold on matching. Changes are saved in the audit log.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form onSubmit={submit} className="flex flex-wrap items-end gap-4">
                    <FormField id="a-monthly" label="PV a month" error={form.errors.monthly_pv}>
                        <Input
                            id="a-monthly"
                            inputMode="decimal"
                            className="w-32"
                            disabled={!editable}
                            value={form.data.monthly_pv}
                            onChange={(e) => form.setData('monthly_pv', e.target.value)}
                        />
                    </FormField>
                    {editable && <Button disabled={form.processing || !form.isDirty}>Save</Button>}
                </form>
            </CardContent>
        </Card>
    );
}

export default function AdminSettings({
    switches,
    leadership,
    monthlyPv,
}: {
    switches: Switch[];
    leadership: { team: string; levels: number };
    monthlyPv: number;
}) {
    const can = useAdminCan();
    const editable = can('settings.edit');
    const form = useForm<Record<string, boolean>>(Object.fromEntries(switches.map((s) => [field(s.key), s.value])));

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.put(route('admin.settings.update'), { preserveScroll: true, onSuccess: () => form.setDefaults() });
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Settings" />
            <div className="max-w-3xl space-y-4 p-4">
                {!editable && (
                    <Alert variant="info">
                        <Lock className="size-4" />
                        <AlertDescription>Only a super-admin can change settings. You can see them.</AlertDescription>
                    </Alert>
                )}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Switches</CardTitle>
                        <CardDescription>
                            What members must confirm, and the rules that are switches until the board decides. Changes are saved in the audit log.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-5">
                            {switches.map((s) => (
                                <div key={s.key} className="flex items-start gap-3">
                                    <Checkbox
                                        id={s.key}
                                        className="mt-0.5"
                                        disabled={!editable}
                                        checked={form.data[field(s.key)]}
                                        onCheckedChange={(v) => form.setData(field(s.key), v === true)}
                                    />
                                    <div className="grid gap-1">
                                        <Label htmlFor={s.key}>{titles[s.key] ?? s.key}</Label>
                                        <p className="text-muted-foreground text-sm">{s.help}</p>
                                    </div>
                                </div>
                            ))}
                            {editable && <Button disabled={form.processing || !form.isDirty}>Save settings</Button>}
                        </form>
                    </CardContent>
                </Card>
                <MonthlyPurchase value={monthlyPv} editable={editable} />
                <LeadershipTeam value={leadership} editable={editable} />
            </div>
        </AdminLayout>
    );
}
