import { type LucideIcon, Inbox } from 'lucide-react';
import { type ReactNode } from 'react';

interface EmptyStateProps {
    title: string;
    description?: string;
    icon?: LucideIcon;
    action?: ReactNode;
}

/** What a list shows when it has nothing in it yet. */
export function EmptyState({ title, description, icon: Icon = Inbox, action }: EmptyStateProps) {
    return (
        <div className="flex flex-col items-center justify-center rounded-lg border border-dashed px-6 py-12 text-center">
            <Icon className="text-muted-foreground mb-3 size-8" aria-hidden />
            <p className="font-medium">{title}</p>
            {description && <p className="text-muted-foreground mt-1 max-w-sm text-sm">{description}</p>}
            {action && <div className="mt-4">{action}</div>}
        </div>
    );
}
