import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import { Paperclip } from 'lucide-react';

export type SupportMessage = {
    id: number;
    from_wiifarm: boolean;
    admin: string | null;
    body: string;
    at: string;
    attachment: { name: string; url: string } | null;
};

const labels: Record<string, [string, 'warning' | 'success' | 'secondary']> = {
    open: ['Waiting for Wiifarm', 'warning'],
    answered: ['Answered', 'success'],
    closed: ['Closed', 'secondary'],
};

export function TicketStatus({ status, admin = false }: { status: string; admin?: boolean }) {
    const [label, variant] = labels[status] ?? [status, 'secondary'];
    return (
        <Badge variant={variant}>
            {admin && status === 'open' ? 'Needs a reply' : admin && status === 'answered' ? 'Waiting for the member' : label}
        </Badge>
    );
}

/** A support ticket's messages, oldest first: the member's on one side, Wiifarm's on the other. */
export function SupportThread({ messages, viewer }: { messages: SupportMessage[]; viewer: 'member' | 'admin' }) {
    return (
        <ol className="space-y-3">
            {messages.map((m) => {
                const mine = viewer === 'member' ? !m.from_wiifarm : m.from_wiifarm;
                return (
                    <li key={m.id} className={cn('flex', mine ? 'justify-end' : 'justify-start')}>
                        <div className={cn('max-w-[85%] rounded-xl border p-3 text-sm', mine ? 'bg-accent/50' : 'bg-card shadow-soft')}>
                            <p className="text-muted-foreground mb-1 text-xs">
                                {m.from_wiifarm
                                    ? viewer === 'admin' && m.admin
                                        ? `Wiifarm support (${m.admin})`
                                        : 'Wiifarm support'
                                    : viewer === 'member'
                                      ? 'You'
                                      : 'Member'}{' '}
                                · {m.at}
                            </p>
                            <p className="whitespace-pre-line">{m.body}</p>
                            {m.attachment && (
                                <a
                                    href={m.attachment.url}
                                    className="text-primary mt-2 inline-flex items-center gap-1 text-xs font-medium underline-offset-4 hover:underline"
                                >
                                    <Paperclip className="size-3" />
                                    {m.attachment.name}
                                </a>
                            )}
                        </div>
                    </li>
                );
            })}
        </ol>
    );
}
