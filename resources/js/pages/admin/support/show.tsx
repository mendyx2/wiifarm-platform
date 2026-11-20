import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { FormField } from '@/components/wf/form-field';
import { SupportThread, TicketStatus, type SupportMessage } from '@/components/wf/support-thread';
import AdminLayout, { useAdminCan } from '@/layouts/admin-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

type Ticket = { id: number; subject: string; status: string; opened: string; member: string; member_id: number };

export default function AdminSupportTicket({ ticket, messages }: { ticket: Ticket; messages: SupportMessage[] }) {
    const can = useAdminCan();
    const form = useForm<{ body: string; file: File | null; close: boolean }>({ body: '', file: null, close: false });
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/admin' },
        { title: 'Support', href: '/admin/support' },
        { title: `#${ticket.id}`, href: `/admin/support/${ticket.id}` },
    ];
    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('admin.support.reply', ticket.id), { forceFormData: true });
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={`Ticket #${ticket.id}`} />
            <div className="max-w-3xl space-y-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-xl font-semibold">{ticket.subject}</h1>
                            <TicketStatus status={ticket.status} admin />
                        </div>
                        <p className="text-muted-foreground text-sm">
                            From{' '}
                            {can('members.view') ? (
                                <Link href={route('admin.members.show', ticket.member_id)} className="underline underline-offset-4">
                                    @{ticket.member}
                                </Link>
                            ) : (
                                `@${ticket.member}`
                            )}{' '}
                            · opened {ticket.opened}
                        </p>
                    </div>
                    {can('support.reply') && ticket.status !== 'closed' && (
                        <Button variant="ghost" size="sm" onClick={() => router.post(route('admin.support.close', ticket.id))}>
                            Close without replying
                        </Button>
                    )}
                </div>
                <SupportThread messages={messages} viewer="admin" />
                {can('support.reply') && (
                    <form onSubmit={submit} className="bg-card shadow-soft space-y-3 rounded-xl border p-4">
                        <FormField id="a-body" label="Reply (the member is told)" error={form.errors.body}>
                            <Textarea id="a-body" rows={4} value={form.data.body} onChange={(e) => form.setData('body', e.target.value)} />
                        </FormField>
                        <FormField id="a-file" label="Attach a photo or PDF (optional)" error={form.errors.file}>
                            <Input
                                id="a-file"
                                type="file"
                                accept="image/jpeg,image/png,image/webp,application/pdf"
                                onChange={(e) => form.setData('file', e.target.files?.[0] ?? null)}
                            />
                        </FormField>
                        <div className="flex items-center gap-2">
                            <Checkbox id="a-close" checked={form.data.close} onCheckedChange={(v) => form.setData('close', v === true)} />
                            <Label htmlFor="a-close">Close the ticket with this reply</Label>
                        </div>
                        <Button disabled={form.processing || !form.data.body.trim()}>Send reply</Button>
                    </form>
                )}
            </div>
        </AdminLayout>
    );
}
