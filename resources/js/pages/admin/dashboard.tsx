import { StatCard } from '@/components/wf/stat-card';
import AdminLayout from '@/layouts/admin-layout';
import { formatKobo } from '@/lib/money';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { BadgeCheck, Ban, CheckCircle2, ChevronRight, Package, UserPlus } from 'lucide-react';

type Todo = { label: string; count: number; amount: number | null; href: string };
type Money = { label: string; tone: 'success' | 'warning' | 'info'; today: number; month: number };
type Members = { total: number; active: number; joinedToday: number; joinedMonth: number; banned: number };
type Pool = { code: string; name: string; balance: number };

interface DashboardProps {
    todo: Todo[];
    money: Money[];
    members: Members | null;
    orders: { toCollect: number; paidToday: number } | null;
    pools: Pool[] | null;
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/admin' }];

const toneText: Record<Money['tone'], string> = { success: 'text-success', warning: 'text-warning', info: 'text-info' };

const n = (value: number) => value.toLocaleString('en-NG');

function Section({ title, children, action }: { title: string; children: React.ReactNode; action?: React.ReactNode }) {
    return (
        <section className="space-y-2">
            <div className="flex items-center justify-between gap-2">
                <h2 className="font-medium">{title}</h2>
                {action}
            </div>
            {children}
        </section>
    );
}

export default function AdminDashboard({ todo, money, members, orders, pools }: DashboardProps) {
    const waiting = todo.filter((t) => t.count > 0);

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin dashboard" />
            <div className="space-y-6 p-4">
                {todo.length > 0 && (
                    <Section title="Waiting for you">
                        {waiting.length === 0 ? (
                            <p className="bg-success-soft text-success flex items-center gap-2 rounded-xl p-4 text-sm">
                                <CheckCircle2 className="size-4" /> Nothing is waiting. All clear.
                            </p>
                        ) : (
                            <ul className="bg-card shadow-soft divide-y rounded-xl border">
                                {waiting.map((t) => (
                                    <li key={t.label}>
                                        <Link href={t.href} className="hover:bg-muted flex items-center justify-between gap-3 px-4 py-3 text-sm">
                                            <span className="min-w-0">
                                                {t.label}
                                                {t.amount !== null && <span className="text-muted-foreground"> · {formatKobo(t.amount)}</span>}
                                            </span>
                                            <span className="flex shrink-0 items-center gap-1">
                                                <span className="bg-accent text-accent-foreground min-w-7 rounded-full px-2 text-center font-semibold tabular-nums">
                                                    {n(t.count)}
                                                </span>
                                                <ChevronRight className="text-muted-foreground size-4" />
                                            </span>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Section>
                )}

                {money.length > 0 && (
                    <Section title="Money">
                        <div className="bg-card shadow-soft overflow-x-auto rounded-xl border">
                            <table className="w-full text-sm">
                                <thead className="text-muted-foreground text-left text-xs">
                                    <tr className="border-b">
                                        <th className="px-4 py-2 font-medium"></th>
                                        <th className="px-4 py-2 text-right font-medium">Today</th>
                                        <th className="px-4 py-2 text-right font-medium">This month</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {money.map((m) => (
                                        <tr key={m.label}>
                                            <td className="px-4 py-3">{m.label}</td>
                                            <td className="px-4 py-3 text-right whitespace-nowrap tabular-nums">{formatKobo(m.today)}</td>
                                            <td className={cn('px-4 py-3 text-right font-medium whitespace-nowrap tabular-nums', toneText[m.tone])}>
                                                {formatKobo(m.month)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </Section>
                )}

                {(members || orders) && (
                    <Section title="Members and orders">
                        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            {members && (
                                <>
                                    <StatCard
                                        label="Paid members"
                                        value={n(members.active)}
                                        icon={BadgeCheck}
                                        tone="success"
                                        hint={`${n(members.total)} accounts in all`}
                                    />
                                    <StatCard
                                        label="Joined this month"
                                        value={n(members.joinedMonth)}
                                        icon={UserPlus}
                                        tone="info"
                                        hint={`${n(members.joinedToday)} today`}
                                    />
                                    <StatCard label="Banned members" value={n(members.banned)} icon={Ban} tone="warning" />
                                </>
                            )}
                            {orders && (
                                <StatCard
                                    label="Orders to collect"
                                    value={n(orders.toCollect)}
                                    icon={Package}
                                    hint={`${n(orders.paidToday)} paid today`}
                                />
                            )}
                        </div>
                    </Section>
                )}

                {pools && (
                    <Section
                        title="Pools"
                        action={
                            <Link href={route('admin.pools')} className="text-primary text-sm font-medium underline-offset-4 hover:underline">
                                Pool ledgers
                            </Link>
                        }
                    >
                        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            {pools.map((p) => (
                                <StatCard key={p.code} label={p.name} value={formatKobo(p.balance)} />
                            ))}
                        </div>
                    </Section>
                )}
            </div>
        </AdminLayout>
    );
}
