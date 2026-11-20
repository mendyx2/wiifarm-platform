import { cn } from '@/lib/utils';

export type Category = { id: number; name: string };

/**
 * Browse a shop by category (specification section 8). Shown only when at
 * least one category has products. "All" shows everything.
 */
export function CategoryFilter({
    categories,
    used,
    value,
    onChange,
}: {
    categories: Category[];
    used: (number | null)[];
    value: number | null;
    onChange: (id: number | null) => void;
}) {
    const shown = categories.filter((c) => used.includes(c.id));
    if (shown.length === 0) return null;
    const chip = (active: boolean) =>
        cn('rounded-full border px-3 py-1 text-sm', active ? 'bg-primary text-primary-foreground border-primary' : 'hover:bg-muted');

    return (
        <nav className="flex flex-wrap gap-2" aria-label="Categories">
            <button type="button" className={chip(value === null)} aria-pressed={value === null} onClick={() => onChange(null)}>
                All
            </button>
            {shown.map((c) => (
                <button key={c.id} type="button" className={chip(value === c.id)} aria-pressed={value === c.id} onClick={() => onChange(c.id)}>
                    {c.name}
                </button>
            ))}
        </nav>
    );
}
