import { RichText } from '@/components/wf/rich-text';
import PublicLayout from '@/layouts/public-layout';
import { Head } from '@inertiajs/react';

type Page = { slug: string; title: string; intro: string | null; body: string | null; image: string | null };

/** A page of the website (about, contact, privacy, terms), as the admins wrote it. */
export default function SitePage({ page }: { page: Page }) {
    return (
        <PublicLayout>
            <Head title={page.title} />
            <article className="max-w-3xl space-y-4">
                <h1 className="text-3xl font-bold tracking-tight">{page.title}</h1>
                {page.intro && <p className="text-muted-foreground text-lg">{page.intro}</p>}
                {page.image && <img src={page.image} alt="" className="shadow-soft w-full rounded-2xl object-cover" />}
                <RichText text={page.body} />
            </article>
        </PublicLayout>
    );
}
