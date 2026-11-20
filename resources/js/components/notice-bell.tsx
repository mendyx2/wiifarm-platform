import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';

/** The bell in the member's header: their unread notices (bonuses paid, messages). */
export function NoticeBell() {
    const { unreadNotices = 0 } = usePage<SharedData>().props;
    const label = unreadNotices > 0 ? `${unreadNotices} new ${unreadNotices === 1 ? 'notice' : 'notices'}` : 'Notices';

    return (
        <Link href={route('notices')} className="hover:bg-muted relative rounded-md p-2" aria-label={label} title={label}>
            <Bell className="size-5" />
            {unreadNotices > 0 && (
                <span className="bg-primary text-primary-foreground absolute -top-0.5 -right-0.5 min-w-5 rounded-full px-1 text-center text-[11px] leading-5 font-semibold tabular-nums">
                    {unreadNotices > 99 ? '99+' : unreadNotices}
                </span>
            )}
        </Link>
    );
}
