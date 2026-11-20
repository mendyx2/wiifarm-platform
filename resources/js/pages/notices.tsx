import { EmptyState } from '@/components/wf/empty-state';
import { NoticeList, type Notice } from '@/components/wf/notice-list';
import { Pagination, type Paginated } from '@/components/wf/pagination';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Notices', href: '/notices' },
];

export default function Notices({ notices }: { notices: Paginated<Notice> }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Notices" />
            <div className="max-w-3xl space-y-4 p-4">
                <h1 className="text-2xl font-bold">Notices</h1>
                <p className="text-muted-foreground text-sm">Every bonus paid to you, and every message we sent you.</p>
                {notices.data.length === 0 ? <EmptyState title="No notices yet" /> : <NoticeList notices={notices.data} />}
                <Pagination page={notices} />
            </div>
        </AppLayout>
    );
}
