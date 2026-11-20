import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { FormField } from '@/components/wf/form-field';
import { SupportThread, TicketStatus, type SupportMessage } from '@/components/wf/support-thread';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

type Ticket = { id: number; subject: string; status: string; opened: string };

export default function SupportTicket({ ticket, messages }: { ticket: Ticket; messages: SupportMessage[] }) {
    const form = useForm<{ body: string; file: File | null }>({ body: '', file: null });
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Support', href: '/support' },
        { title: ticket.subject, href: `/support/${ticket.id}` },
    ];
    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('support.reply', ticket.id), { forceFormData: true, preserveScroll: true, onSuccess: () => form.reset() });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={ticket.subject} />
            <div className="max-w-3xl space-y-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div className="flex items-center gap-2">
                        <h1 className="text-xl font-semibold">{ticket.subject}</h1>
                        <TicketStatus status={ticket.status} />
                    </div>
                    {ticket.status !== 'closed' && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => router.post(route('support.close', ticket.id), {}, { preserveScroll: true })}
                        >
                            My question is answered: close
                        </Button>
                    )}
                </div>
                <SupportThread messages={messages} viewer="member" />
                <form onSubmit={submit} className="bg-card shadow-soft space-y-3 rounded-xl border p-4">
                    <FormField
                        id="r-body"
                        label={ticket.status === 'closed' ? 'Write again (this reopens the ticket)' : 'Reply'}
                        error={form.errors.body}
                    >
                        <Textarea id="r-body" rows={3} value={form.data.body} onChange={(e) => form.setData('body', e.target.value)} />
                    </FormField>
                    <FormField id="r-file" label="Attach a photo or PDF (optional)" error={form.errors.file}>
                        <Input
                            id="r-file"
                            type="file"
                            accept="image/jpeg,image/png,image/webp,application/pdf"
                            onChange={(e) => form.setData('file', e.target.files?.[0] ?? null)}
                        />
                    </FormField>
                    <Button disabled={form.processing || !form.data.body.trim()}>Send</Button>
                </form>
            </div>
        </AppLayout>
    );
}
