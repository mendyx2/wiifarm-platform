import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { FormField } from '@/components/wf/form-field';
import { RichText } from '@/components/wf/rich-text';
import AdminLayout from '@/layouts/admin-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

type Page = {
    slug: string;
    name: string;
    title: string;
    intro: string | null;
    body: string | null;
    image: string | null;
    seo_title: string | null;
    seo_description: string | null;
};

export default function PageEdit({ page, url }: { page: Page; url: string }) {
    const form = useForm<{
        title: string;
        intro: string;
        body: string;
        seo_title: string;
        seo_description: string;
        image: File | null;
        remove_image: boolean;
    }>({
        title: page.title,
        intro: page.intro ?? '',
        body: page.body ?? '',
        seo_title: page.seo_title ?? '',
        seo_description: page.seo_description ?? '',
        image: null,
        remove_image: false,
    });
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/admin' },
        { title: 'Website', href: '/admin/website' },
        { title: page.name, href: `/admin/website/${page.slug}` },
    ];
    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('admin.content.update', page.slug), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => form.setData('image', null),
        });
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={page.name} />
            <form onSubmit={submit} className="grid max-w-6xl gap-4 p-4 lg:grid-cols-2">
                <div className="space-y-4">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">{page.name}</CardTitle>
                            <CardDescription>
                                <a href={url} target="_blank" rel="noreferrer" className="underline underline-offset-4">
                                    View the page
                                </a>
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <FormField id="p-title" label="Title" error={form.errors.title}>
                                <Input id="p-title" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                            </FormField>
                            <FormField id="p-intro" label="Short line under the title" error={form.errors.intro}>
                                <Input id="p-intro" value={form.data.intro} onChange={(e) => form.setData('intro', e.target.value)} />
                            </FormField>
                            <FormField
                                id="p-body"
                                label="Text"
                                error={form.errors.body}
                                hint='Leave a blank line between paragraphs. Start a line with "## " for a heading, or "- " for a list item.'
                            >
                                <Textarea id="p-body" rows={14} value={form.data.body} onChange={(e) => form.setData('body', e.target.value)} />
                            </FormField>
                            <FormField id="p-image" label="Picture (JPG, PNG or WebP, up to 4 MB)" error={form.errors.image}>
                                <Input
                                    id="p-image"
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    onChange={(e) => form.setData('image', e.target.files?.[0] ?? null)}
                                />
                            </FormField>
                            {page.image && (
                                <div className="flex items-center gap-3">
                                    <img src={page.image} alt="" className="h-16 w-24 rounded-md border object-cover" />
                                    <Checkbox
                                        id="p-remove"
                                        checked={form.data.remove_image}
                                        onCheckedChange={(v) => form.setData('remove_image', v === true)}
                                    />
                                    <Label htmlFor="p-remove">Remove the picture</Label>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Search engines and link previews</CardTitle>
                            <CardDescription>
                                What Google and WhatsApp or Facebook previews show. Empty uses the title and short line.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <FormField id="p-seo-title" label="Title (up to 70 characters)" error={form.errors.seo_title}>
                                <Input
                                    id="p-seo-title"
                                    maxLength={70}
                                    value={form.data.seo_title}
                                    onChange={(e) => form.setData('seo_title', e.target.value)}
                                />
                            </FormField>
                            <FormField id="p-seo-desc" label="Description (up to 170 characters)" error={form.errors.seo_description}>
                                <Textarea
                                    id="p-seo-desc"
                                    rows={2}
                                    maxLength={170}
                                    value={form.data.seo_description}
                                    onChange={(e) => form.setData('seo_description', e.target.value)}
                                />
                            </FormField>
                        </CardContent>
                    </Card>
                    <Button disabled={form.processing || !form.isDirty}>Save</Button>
                </div>
                <Card className="h-fit">
                    <CardHeader>
                        <CardTitle className="text-base">Preview</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <h1 className="text-2xl font-bold">{form.data.title}</h1>
                        {form.data.intro && <p className="text-muted-foreground">{form.data.intro}</p>}
                        <RichText text={form.data.body} className="text-sm" />
                    </CardContent>
                </Card>
            </form>
        </AdminLayout>
    );
}
