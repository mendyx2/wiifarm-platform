import { formatKobo } from '@/lib/money';
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { Gift, Mail } from 'lucide-react';

export type Notice = {
    id: string;
    at: string;
    read: boolean;
    kind: string;
    title: string;
    text: string | null;
    amount: number | null;
    url: string | null;
};

/** Bonuses paid and messages, newest first. */
export function NoticeList({ notices }: { notices: Notice[] }) {
    return (
        <ul className="bg-card shadow-soft divide-y rounded-xl border">
            {notices.map((n) => {
                const Icon = n.kind === 'bonus' ? Gift : Mail;
                return (
                    <li key={n.id} className={cn('flex items-start gap-3 px-4 py-3 text-sm', !n.read && 'bg-accent/40')}>
                        <Icon className={cn('mt-0.5 size-4 shrink-0', n.kind === 'bonus' ? 'text-success' : 'text-muted-foreground')} />
                        <div className="min-w-0 flex-1">
                            <p className="font-medium">
                                {n.kind === 'bonus' ? `${n.title} paid` : n.title}
                                {!n.read && <span className="text-primary ml-2 text-xs font-semibold">New</span>}
                            </p>
                            {n.text && <p className="text-muted-foreground text-xs whitespace-pre-line">{n.text}</p>}
                            <p className="text-muted-foreground mt-0.5 text-xs">
                                {n.at}
                                {n.url && (
                                    <>
                                        {' · '}
                                        <Link href={n.url} className="text-primary font-medium underline-offset-4 hover:underline">
                                            Open
                                        </Link>
                                    </>
                                )}
                            </p>
                        </div>
                        {n.amount !== null && <span className="text-success shrink-0 font-medium tabular-nums">+{formatKobo(n.amount)}</span>}
                    </li>
                );
            })}
        </ul>
    );
}
