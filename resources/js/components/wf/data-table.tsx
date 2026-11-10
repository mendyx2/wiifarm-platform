import { cn } from '@/lib/utils';
import { type ReactNode } from 'react';
import { EmptyState } from './empty-state';

export interface Column<Row> {
    key: string;
    header: string;
    cell: (row: Row) => ReactNode;
    align?: 'left' | 'right';
    className?: string;
}

interface DataTableProps<Row> {
    columns: Column<Row>[];
    rows: Row[];
    rowKey: (row: Row) => string | number;
    emptyTitle?: string;
    emptyDescription?: string;
}

/** A plain, readable table. Numbers align right; on small screens it scrolls sideways inside its card. */
export function DataTable<Row>({ columns, rows, rowKey, emptyTitle = 'Nothing here yet', emptyDescription }: DataTableProps<Row>) {
    if (rows.length === 0) {
        return <EmptyState title={emptyTitle} description={emptyDescription} />;
    }

    return (
        <div className="overflow-x-auto rounded-lg border">
            <table className="w-full text-sm">
                <thead className="bg-muted/60 text-muted-foreground">
                    <tr>
                        {columns.map((c) => (
                            <th
                                key={c.key}
                                scope="col"
                                className={cn('px-4 py-3 font-medium', c.align === 'right' ? 'text-right' : 'text-left', c.className)}
                            >
                                {c.header}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row) => (
                        <tr key={rowKey(row)} className="border-t">
                            {columns.map((c) => (
                                <td key={c.key} className={cn('px-4 py-3', c.align === 'right' && 'text-right tabular-nums', c.className)}>
                                    {c.cell(row)}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
