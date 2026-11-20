import AdminLayout from '@/layouts/admin-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';

type Row = { slug: string; name: string; title: string; updated_at: string | null; url: string };

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin' },
    { title: 'Website', href: '/admin/website' },
];

export default function ContentPages({ pages }: { pages: Row[] }) {
    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Website" />
            <div className="max-w-3xl space-y-4 p-4">
                <p className="text-muted-foreground text-sm">
                    The pages of the public website. Click one to change its text, picture, and what search engines and link previews show.
                </p>
                <ul className="bg-card shadow-soft divide-y rounded-xl border">
                    {pages.map((p) => (
                        <li key={p.slug} className="flex items-center justify-between gap-3 px-4 py-3">
                            <Link href={route('admin.content.edit', p.slug)} className="group min-w-0 flex-1">
                                <span className="font-medium group-hover:underline">{p.name}</span>
                                <span className="text-muted-foreground block truncate text-xs">
                                    {p.title}
                                    {p.updated_at && ` · changed ${p.updated_at}`}
                                </span>
                            </Link>
                            <a
                                href={p.url}
                                target="_blank"
                                rel="noreferrer"
                                className="text-primary shrink-0 text-xs underline-offset-4 hover:underline"
                            >
                                View
                            </a>
                            <ChevronRight className="text-muted-foreground size-4 shrink-0" />
                        </li>
                    ))}
                </ul>
            </div>
        </AdminLayout>
    );
}
