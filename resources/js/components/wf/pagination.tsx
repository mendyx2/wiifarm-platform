import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';

/** The page data Laravel sends with a paginated list. */
export interface Paginated<Row> {
    data: Row[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

/** "Showing 1–25 of 140" with Previous and Next. Hidden when everything fits on one page. */
export function Pagination({ page }: { page: Paginated<unknown> }) {
    if (page.last_page <= 1) {
        return page.total > 0 ? <p className="text-muted-foreground text-sm">{page.total.toLocaleString('en-NG')} in total</p> : null;
    }

    const linkClass = (enabled: boolean) =>
        cn('rounded-md border px-3 py-1.5 text-sm', enabled ? 'hover:bg-muted' : 'text-muted-foreground pointer-events-none opacity-50');

    return (
        <nav className="flex flex-wrap items-center justify-between gap-3" aria-label="Pages">
            <p className="text-muted-foreground text-sm">
                Showing {page.from}–{page.to} of {page.total.toLocaleString('en-NG')}
            </p>
            <div className="flex gap-2">
                <Link href={page.prev_page_url ?? '#'} preserveScroll className={linkClass(!!page.prev_page_url)}>
                    Previous
                </Link>
                <Link href={page.next_page_url ?? '#'} preserveScroll className={linkClass(!!page.next_page_url)}>
                    Next
                </Link>
            </div>
        </nav>
    );
}
