import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { DataTable } from '@/components/wf/data-table';
import { FormField } from '@/components/wf/form-field';
import { Pagination, type Paginated } from '@/components/wf/pagination';
import { TicketStatus } from '@/components/wf/support-thread';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

type Ticket = { id: number; subject: string; status: string; opened: string; last: string | null };

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Support', href: '/support' }];

export default function Support({ tickets }: { tickets: Paginated<Ticket> }) {
    const form = useForm<{ subject: string; body: string; file: File | null }>({ subject: '', body: '', file: null });
    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('support.store'), { forceFormData: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Support" />
            <div className="max-w-3xl space-y-6 p-4">
                <form onSubmit={submit} className="bg-card shadow-soft space-y-4 rounded-xl border p-4">
                    <h2 className="font-medium">Ask Wiifarm for help</h2>
                    <FormField id="s-subject" label="Subject" error={form.errors.subject}>
                        <Input id="s-subject" value={form.data.subject} onChange={(e) => form.setData('subject', e.target.value)} />
                    </FormField>
                    <FormField id="s-body" label="Your message" error={form.errors.body}>
                        <Textarea id="s-body" rows={5} value={form.data.body} onChange={(e) => form.setData('body', e.target.value)} />
                    </FormField>
                    <FormField id="s-file" label="Attach a photo or PDF (optional, up to 5 MB)" error={form.errors.file}>
                        <Input
                            id="s-file"
                            type="file"
                            accept="image/jpeg,image/png,image/webp,application/pdf"
                            onChange={(e) => form.setData('file', e.target.files?.[0] ?? null)}
                        />
                    </FormField>
                    <Button disabled={form.processing || !form.data.subject.trim() || !form.data.body.trim()}>Send</Button>
                </form>
                <section className="space-y-2">
                    <h2 className="font-medium">Your tickets</h2>
                    <DataTable
                        rows={tickets.data}
                        rowKey={(t) => t.id}
                        emptyTitle="No tickets yet"
                        columns={[
                            {
                                key: 'subject',
                                header: 'Subject',
                                cell: (t) => (
                                    <Link href={route('support.show', t.id)} className="font-medium hover:underline">
                                        {t.subject}
                                    </Link>
                                ),
                            },
                            { key: 'status', header: 'Status', cell: (t) => <TicketStatus status={t.status} /> },
                            { key: 'last', header: 'Last message', cell: (t) => t.last, className: 'whitespace-nowrap' },
                        ]}
                    />
                    <Pagination page={tickets} />
                </section>
            </div>
        </AppLayout>
    );
}
