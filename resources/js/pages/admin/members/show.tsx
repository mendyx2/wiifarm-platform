import { KycBadge, MemberStatusBadge, PaidBadge } from '@/components/admin/badges';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { DataTable } from '@/components/wf/data-table';
import AdminLayout, { useAdminCan } from '@/layouts/admin-layout';
import { auditActionName } from '@/lib/audit-actions';
import { countries, countryName } from '@/lib/countries';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { Ban, ShieldAlert } from 'lucide-react';
import { FormEventHandler, type ReactNode } from 'react';

interface Member {
    id: number;
    username: string;
    first_name: string;
    last_name: string;
    name: string;
    email: string;
    email_verified: boolean;
    dial_code: string;
    mobile: string;
    mobile_verified: boolean;
    country: string;
    state: string | null;
    city: string | null;
    address: string | null;
    status: string;
    ban_reason: string | null;
    withdrawals_blocked: boolean;
    withdrawals_block_reason: string | null;
    kyc_status: string;
    paid: boolean;
    activated_at: string | null;
    joined: string;
    two_factor: boolean;
    is_company: boolean;
    sponsor: { id: number; username: string; name: string } | null;
    referrals: number;
}

interface ShowProps {
    member: Member;
    kyc: { id: number; status: string; submitted_at: string } | null;
    sponsorChanges: { id: number; from: string | null; to: string | null; reason: string; at: string }[];
    history: { id: number; action: string; admin: string | null; note: string | null; at: string }[];
}

function Detail({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="flex flex-wrap justify-between gap-2 px-4 py-2.5">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="text-right font-medium">{children}</dd>
        </div>
    );
}

function Verified({ ok }: { ok: boolean }) {
    return ok ? <Badge variant="success">confirmed</Badge> : <Badge variant="outline">not confirmed</Badge>;
}

/** One action with a required reason: ban, unban, block or open withdrawals. */
function ReasonAction({
    url,
    field,
    label,
    placeholder,
    button,
    destructive,
}: {
    url: string;
    field: string;
    label: string;
    placeholder: string;
    button: string;
    destructive?: boolean;
}) {
    const form = useForm({ reason: '' });
    const errors = form.errors as Record<string, string>;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(url, { preserveScroll: true, onSuccess: () => form.reset() });
    };

    return (
        <form onSubmit={submit} className="grid gap-2">
            <Label htmlFor={field}>{label}</Label>
            <Textarea
                id={field}
                rows={2}
                placeholder={placeholder}
                value={form.data.reason}
                onChange={(e) => form.setData('reason', e.target.value)}
            />
            <InputError message={errors[field] ?? form.errors.reason} />
            <Button
                className="w-fit"
                variant={destructive ? 'destructive' : 'secondary'}
                disabled={form.processing || form.data.reason.trim() === ''}
            >
                {button}
            </Button>
        </form>
    );
}

function EditDetails({ member }: { member: Member }) {
    const form = useForm({
        first_name: member.first_name,
        last_name: member.last_name,
        email: member.email,
        dial_code: member.dial_code,
        mobile: member.mobile,
        country: member.country,
        state: member.state ?? '',
        city: member.city ?? '',
        address: member.address ?? '',
        note: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.patch(route('admin.members.update', member.id), {
            preserveScroll: true,
            onSuccess: () => {
                form.setData('note', '');
                form.setDefaults({ ...form.data, note: '' });
            },
        });
    };

    const text = (key: 'first_name' | 'last_name' | 'email' | 'dial_code' | 'mobile' | 'state' | 'city' | 'address', label: string) => (
        <div className="grid gap-1.5">
            <Label htmlFor={`edit-${key}`}>{label}</Label>
            <Input id={`edit-${key}`} value={form.data[key]} onChange={(e) => form.setData(key, e.target.value)} />
            <InputError message={form.errors[key]} />
        </div>
    );

    return (
        <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
            {text('first_name', 'First name')}
            {text('last_name', 'Last name')}
            <div className="sm:col-span-2">{text('email', 'Email address')}</div>
            {text('dial_code', 'Dialling code')}
            {text('mobile', 'Mobile number')}
            <div className="grid gap-1.5">
                <Label htmlFor="edit-country">Country</Label>
                <select
                    id="edit-country"
                    className="border-input bg-background h-10 rounded-md border px-3 text-sm"
                    value={form.data.country}
                    onChange={(e) => form.setData('country', e.target.value)}
                >
                    {!countries.some(([c]) => c === form.data.country) && <option value={form.data.country}>{form.data.country}</option>}
                    {countries.map(([code, name]) => (
                        <option key={code} value={code}>
                            {name}
                        </option>
                    ))}
                </select>
                <InputError message={form.errors.country} />
            </div>
            {text('state', 'State')}
            {text('city', 'City')}
            {text('address', 'Address')}
            <div className="grid gap-1.5 sm:col-span-2">
                <Label htmlFor="edit-note">Why are you changing these details?</Label>
                <Input
                    id="edit-note"
                    placeholder="e.g. Member asked by phone; identity checked"
                    value={form.data.note}
                    onChange={(e) => form.setData('note', e.target.value)}
                />
                <p className="text-muted-foreground text-xs">
                    Saved in the audit log. A new email or phone number must be confirmed again by the member.
                </p>
                <InputError message={form.errors.note} />
            </div>
            <Button className="w-fit" disabled={form.processing || !form.isDirty}>
                Save details
            </Button>
        </form>
    );
}

function ChangeSponsorForm({ member }: { member: Member }) {
    const form = useForm({ sponsor: '', reason: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('admin.members.sponsor', member.id), { preserveScroll: true, onSuccess: () => form.reset() });
    };

    return (
        <form onSubmit={submit} className="grid gap-2">
            <Label htmlFor="new-sponsor">New sponsor's username</Label>
            <Input id="new-sponsor" value={form.data.sponsor} onChange={(e) => form.setData('sponsor', e.target.value)} />
            <InputError message={form.errors.sponsor} />
            <Label htmlFor="sponsor-reason">Reason</Label>
            <Textarea id="sponsor-reason" rows={2} value={form.data.reason} onChange={(e) => form.setData('reason', e.target.value)} />
            <InputError message={form.errors.reason} />
            <Button className="w-fit" variant="secondary" disabled={form.processing || !form.data.sponsor.trim() || !form.data.reason.trim()}>
                Change sponsor
            </Button>
        </form>
    );
}

export default function MemberShow({ member, kyc, sponsorChanges, history }: ShowProps) {
    const can = useAdminCan();
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/admin' },
        { title: 'Members', href: '/admin/members' },
        { title: `@${member.username}`, href: `/admin/members/${member.id}` },
    ];
    const banned = member.status === 'banned';

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={`${member.name} (@${member.username})`} />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">{member.name}</h1>
                        <p className="text-muted-foreground text-sm">
                            @{member.username} · member #{member.id} · joined {member.joined}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-1">
                        <MemberStatusBadge status={member.status} />
                        <PaidBadge paid={member.paid} />
                        <KycBadge status={member.kyc_status} />
                        {member.is_company && <Badge variant="info">Company account</Badge>}
                    </div>
                </div>

                {banned && (
                    <Alert variant="destructive">
                        <Ban className="size-4" />
                        <AlertTitle>This member is banned</AlertTitle>
                        <AlertDescription>{member.ban_reason}</AlertDescription>
                    </Alert>
                )}
                {member.withdrawals_blocked && (
                    <Alert variant="warning">
                        <ShieldAlert className="size-4" />
                        <AlertTitle>Withdrawals are blocked</AlertTitle>
                        <AlertDescription>{member.withdrawals_block_reason}</AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Details</CardTitle>
                        </CardHeader>
                        <CardContent className="p-0 pb-2">
                            <dl className="divide-y text-sm">
                                <Detail label="Email">
                                    {member.email} <Verified ok={member.email_verified} />
                                </Detail>
                                <Detail label="Phone">
                                    +{member.dial_code} {member.mobile} <Verified ok={member.mobile_verified} />
                                </Detail>
                                <Detail label="Country">{countryName(member.country)}</Detail>
                                <Detail label="State and city">{[member.state, member.city].filter(Boolean).join(', ') || '—'}</Detail>
                                <Detail label="Address">{member.address || '—'}</Detail>
                                <Detail label="Sponsor">
                                    {member.sponsor ? (
                                        <Link href={route('admin.members.show', member.sponsor.id)} className="underline underline-offset-4">
                                            @{member.sponsor.username}
                                        </Link>
                                    ) : (
                                        '—'
                                    )}
                                </Detail>
                                <Detail label="Members they referred">{member.referrals}</Detail>
                                <Detail label="Package paid">{member.activated_at ?? 'Not yet'}</Detail>
                                <Detail label="Two-factor sign-in">{member.two_factor ? 'On' : 'Off'}</Detail>
                                <Detail label="KYC">
                                    {kyc ? (
                                        <Link href={route('admin.kyc.show', kyc.id)} className="underline underline-offset-4">
                                            Latest submission, {kyc.submitted_at}
                                        </Link>
                                    ) : (
                                        'Nothing submitted'
                                    )}
                                </Detail>
                            </dl>
                        </CardContent>
                    </Card>

                    <div className="space-y-6">
                        {(can('members.ban') || can('members.block_withdrawals')) && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">Account standing</CardTitle>
                                    <CardDescription>Each action needs a reason and is saved in the audit log.</CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-6">
                                    {can('members.ban') &&
                                        !member.is_company &&
                                        (banned ? (
                                            <ReasonAction
                                                url={route('admin.members.unban', member.id)}
                                                field="unban_reason"
                                                label="Lift the ban"
                                                placeholder="Why the ban is lifted"
                                                button="Lift the ban"
                                            />
                                        ) : (
                                            <ReasonAction
                                                url={route('admin.members.ban', member.id)}
                                                field="ban_reason"
                                                label="Ban this member"
                                                placeholder="The member sees this reason when they try to sign in"
                                                button="Ban and sign out"
                                                destructive
                                            />
                                        ))}
                                    {can('members.block_withdrawals') &&
                                        (member.withdrawals_blocked ? (
                                            <ReasonAction
                                                url={route('admin.members.unblock-withdrawals', member.id)}
                                                field="unblock_reason"
                                                label="Open withdrawals again"
                                                placeholder="Why withdrawals are opened"
                                                button="Open withdrawals"
                                            />
                                        ) : (
                                            <ReasonAction
                                                url={route('admin.members.block-withdrawals', member.id)}
                                                field="block_reason"
                                                label="Block withdrawals"
                                                placeholder="The member sees this reason when they try to withdraw"
                                                button="Block withdrawals"
                                                destructive
                                            />
                                        ))}
                                </CardContent>
                            </Card>
                        )}

                        {can('members.change_sponsor') && !member.is_company && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">Sponsor</CardTitle>
                                    <CardDescription>
                                        Moves this member under a different sponsor. The people they referred stay with them. The change is logged.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <ChangeSponsorForm member={member} />
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </div>

                {can('members.edit') && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Edit details</CardTitle>
                            <CardDescription>The username cannot be changed.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <EditDetails member={member} />
                        </CardContent>
                    </Card>
                )}

                {sponsorChanges.length > 0 && (
                    <section className="space-y-2">
                        <h2 className="font-medium">Sponsor changes</h2>
                        <DataTable
                            rows={sponsorChanges}
                            rowKey={(c) => c.id}
                            columns={[
                                { key: 'at', header: 'When', cell: (c) => c.at, className: 'whitespace-nowrap' },
                                { key: 'from', header: 'From', cell: (c) => (c.from ? `@${c.from}` : '—') },
                                { key: 'to', header: 'To', cell: (c) => (c.to ? `@${c.to}` : '—') },
                                { key: 'reason', header: 'Reason', cell: (c) => c.reason },
                            ]}
                        />
                    </section>
                )}

                <section className="space-y-2">
                    <h2 className="font-medium">Admin actions on this member</h2>
                    <DataTable
                        rows={history}
                        rowKey={(h) => h.id}
                        emptyTitle="No admin actions yet"
                        columns={[
                            { key: 'at', header: 'When', cell: (h) => h.at, className: 'whitespace-nowrap' },
                            { key: 'action', header: 'Action', cell: (h) => auditActionName(h.action) },
                            { key: 'admin', header: 'By', cell: (h) => (h.admin ? `@${h.admin}` : 'System') },
                            { key: 'note', header: 'Reason', cell: (h) => h.note ?? '—' },
                        ]}
                    />
                </section>
            </div>
        </AdminLayout>
    );
}
