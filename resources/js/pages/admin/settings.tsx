import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
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
};

/** Form field names cannot hold dots, so "verification.email_required" travels as "verification_email_required". */
const field = (key: string) => key.replace(/\./g, '_');

export default function AdminSettings({ switches }: { switches: Switch[] }) {
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
                        <CardTitle className="text-base">Verification</CardTitle>
                        <CardDescription>
                            What a member must confirm before they can use their dashboard. Changes are saved in the audit log.
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
            </div>
        </AdminLayout>
    );
}
