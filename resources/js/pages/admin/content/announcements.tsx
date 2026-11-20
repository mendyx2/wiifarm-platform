import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { FormField, selectClass } from '@/components/wf/form-field';
import AdminLayout from '@/layouts/admin-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

type Row = {
    id: number;
    title: string;
    body: string;
    tone: string;
    is_active: boolean;
    starts_at: string | null;
    ends_at: string | null;
    showing: boolean;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin' },
    { title: 'Announcements', href: '/admin/announcements' },
];

function AnnouncementForm({ row }: { row: Row | null }) {
    const form = useForm({
        title: row?.title ?? '',
        body: row?.body ?? '',
        tone: row?.tone ?? 'info',
        starts_at: row?.starts_at ?? '',
        ends_at: row?.ends_at ?? '',
        is_active: row?.is_active ?? true,
    });
    const id = (k: string) => `${row?.id ?? 'new'}-${k}`;
    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (row) form.put(route('admin.announcements.update', row.id), { preserveScroll: true });
        else form.post(route('admin.announcements.store'), { preserveScroll: true, onSuccess: () => form.reset() });
    };

    return (
        <Card>
            <CardHeader className="pb-3">
                <div className="flex flex-wrap items-center gap-2">
                    <CardTitle className="text-base">{row ? row.title : 'New announcement'}</CardTitle>
                    {row && <Badge variant={row.showing ? 'success' : 'secondary'}>{row.showing ? 'Showing now' : 'Not showing'}</Badge>}
                </div>
            </CardHeader>
            <CardContent>
                <form onSubmit={submit} className="grid gap-3 sm:grid-cols-3">
                    <div className="sm:col-span-2">
                        <FormField id={id('title')} label="Title" error={form.errors.title}>
                            <Input id={id('title')} value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                        </FormField>
                    </div>
                    <FormField id={id('tone')} label="Kind" error={form.errors.tone}>
                        <select id={id('tone')} className={selectClass} value={form.data.tone} onChange={(e) => form.setData('tone', e.target.value)}>
                            <option value="info">Information</option>
                            <option value="important">Important</option>
                        </select>
                    </FormField>
                    <div className="sm:col-span-3">
                        <FormField id={id('body')} label="Message" error={form.errors.body}>
                            <Textarea id={id('body')} rows={3} value={form.data.body} onChange={(e) => form.setData('body', e.target.value)} />
                        </FormField>
                    </div>
                    <FormField id={id('starts')} label="From (empty: now)" error={form.errors.starts_at}>
                        <Input
                            id={id('starts')}
                            type="datetime-local"
                            value={form.data.starts_at}
                            onChange={(e) => form.setData('starts_at', e.target.value)}
                        />
                    </FormField>
                    <FormField id={id('ends')} label="Until (empty: no end)" error={form.errors.ends_at}>
                        <Input
                            id={id('ends')}
                            type="datetime-local"
                            value={form.data.ends_at}
                            onChange={(e) => form.setData('ends_at', e.target.value)}
                        />
                    </FormField>
                    <div className="flex items-center gap-2 pt-6">
                        <Checkbox id={id('on')} checked={form.data.is_active} onCheckedChange={(v) => form.setData('is_active', v === true)} />
                        <Label htmlFor={id('on')}>Switched on</Label>
                    </div>
                    <InputError message={form.errors.is_active} />
                    <div className="flex gap-2 sm:col-span-3">
                        <Button disabled={form.processing || (row !== null && !form.isDirty)}>{row ? 'Save' : 'Publish'}</Button>
                        {row && (
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() =>
                                    confirm('Delete this announcement?') &&
                                    router.delete(route('admin.announcements.destroy', row.id), { preserveScroll: true })
                                }
                            >
                                Delete
                            </Button>
                        )}
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}

export default function Announcements({ announcements }: { announcements: Row[] }) {
    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Announcements" />
            <div className="max-w-4xl space-y-4 p-4">
                <p className="text-muted-foreground text-sm">
                    Notices to all members, shown at the top of their dashboard while they run. Times are in Lagos time.
                </p>
                <AnnouncementForm row={null} />
                {announcements.map((a) => (
                    <AnnouncementForm key={a.id} row={a} />
                ))}
            </div>
        </AdminLayout>
    );
}
