import { StatCard } from '@/components/wf/stat-card';
import AdminLayout from '@/layouts/admin-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { BadgeCheck, Ban, FileClock, Users } from 'lucide-react';

interface DashboardProps {
    stats: { members: number; activeMembers: number; bannedMembers: number; kycPending: number };
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/admin' }];

const n = (value: number) => value.toLocaleString('en-NG');

export default function AdminDashboard({ stats }: DashboardProps) {
    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin dashboard" />
            <div className="grid gap-4 p-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard label="Members" value={n(stats.members)} icon={Users} />
                <StatCard label="Paid (activated) members" value={n(stats.activeMembers)} icon={BadgeCheck} tone="success" />
                <StatCard label="Banned members" value={n(stats.bannedMembers)} icon={Ban} tone="warning" />
                <StatCard label="KYC waiting for review" value={n(stats.kycPending)} icon={FileClock} tone="info" />
            </div>
        </AdminLayout>
    );
}
