import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { FormField } from '@/components/wf/form-field';
import AdminLayout, { useAdminCan } from '@/layouts/admin-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

type Props = {
    event: { key: string; name: string; who: string; placeholders: string[] };
    template: { subject: string; body: string; enabled: boolean; edited: boolean };
    default: { subject: string; body: string };
};

/** What the member will read, with sample values in the placeholders. */
function fill(text: string) {
    const samples: Record<string, string> = {
        name: 'Ada',
        username: 'ada_okafor',
        member: '@chika_o',
        package: 'Builder',
        amount: '₦25,000.00',
        payable: '₦24,500.00',
        reference: 'TRF-20261120-01',
        reason: 'The photo of the ID card is not clear.',
        award: 'Diamond',
        order: 'WF-10234',
        code: 'K7Q-4MX-9PD',
        month: 'November 2026',
        deadline: '7 December',
        bought: '3',
        needed: '10.5',
    };
    return text.replace(/\{\{\s*([a-z0-9_]+)\s*\}\}/g, (all, key: string) => samples[key] ?? all);
}

export default function MessageEdit({ event, template, default: original }: Props) {
    const can = useAdminCan();
    const editable = can('messages.edit');
    const form = useForm({ subject: template.subject, body: template.body, enabled: template.enabled });
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/admin' },
        { title: 'Messages', href: '/admin/messages' },
        { title: event.name, href: `/admin/messages/${event.key}` },
    ];

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.put(route('admin.messages.update', { event: event.key }));
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={event.name} />
            <div className="grid max-w-5xl gap-4 p-4 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">{event.name}</CardTitle>
                        <CardDescription>To: {event.who}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-4">
                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="m-enabled"
                                    disabled={!editable}
                                    checked={form.data.enabled}
                                    onCheckedChange={(v) => form.setData('enabled', v === true)}
                                />
                                <Label htmlFor="m-enabled">Send this message</Label>
                            </div>
                            <FormField id="m-subject" label="Subject" error={form.errors.subject}>
                                <Input
                                    id="m-subject"
                                    disabled={!editable}
                                    value={form.data.subject}
                                    onChange={(e) => form.setData('subject', e.target.value)}
                                />
                            </FormField>
                            <FormField
                                id="m-body"
                                label="Text"
                                error={form.errors.body}
                                hint="Leave a blank line between paragraphs. The button at the end is added for you."
                            >
                                <Textarea
                                    id="m-body"
                                    rows={9}
                                    disabled={!editable}
                                    value={form.data.body}
                                    onChange={(e) => form.setData('body', e.target.value)}
                                />
                            </FormField>
                            <div className="text-sm">
                                <p className="text-muted-foreground mb-1">You can use:</p>
                                <div className="flex flex-wrap gap-1">
                                    {event.placeholders.map((p) => (
                                        <code key={p} className="bg-muted rounded px-1.5 py-0.5 text-xs">{`{{${p}}}`}</code>
                                    ))}
                                </div>
                            </div>
                            {editable && (
                                <div className="flex flex-wrap gap-2">
                                    <Button disabled={form.processing || !form.isDirty}>Save</Button>
                                    {template.edited && (
                                        <Button
                                            type="button"
                                            variant="secondary"
                                            onClick={() =>
                                                confirm('Set this message back to the original text?') &&
                                                router.delete(route('admin.messages.reset', { event: event.key }))
                                            }
                                        >
                                            Back to the original text
                                        </Button>
                                    )}
                                </div>
                            )}
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">What the member reads</CardTitle>
                        <CardDescription>With sample details in place of the placeholders.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3 text-sm">
                        <p className="font-medium">{fill(form.data.subject)}</p>
                        <p>Hello {fill('{{name}}')},</p>
                        {form.data.body
                            .trim()
                            .split(/\n\s*\n/)
                            .map((para, i) => (
                                <p key={i} className="whitespace-pre-line">
                                    {fill(para)}
                                </p>
                            ))}
                        {template.edited && (
                            <details className="text-muted-foreground pt-2 text-xs">
                                <summary className="cursor-pointer">The original text</summary>
                                <p className="mt-2 font-medium">{original.subject}</p>
                                <p className="mt-1 whitespace-pre-line">{original.body}</p>
                            </details>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
