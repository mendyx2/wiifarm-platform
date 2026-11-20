import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { NoticeList, type Notice } from '@/components/wf/notice-list';
import { PvSummary } from '@/components/wf/pv-summary';
import { StatCard } from '@/components/wf/stat-card';
import AppLayout from '@/layouts/app-layout';
import { formatKobo } from '@/lib/money';
import { pv, type PvTotals } from '@/lib/network';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Banknote, Gift, PiggyBank, ShoppingBasket, TrendingUp } from 'lucide-react';
import { useState } from 'react';

type Member = {
    first_name: string;
    package: { name: string; pv: number; cap: number; per_match: number } | null;
    joined: string;
    kyc: string;
    referral_link: string;
};

type Props = {
    member: Member | null;
    money?: { main: number; visa: number; product: number; autoship: number; bonuses: number; earned: number };
    pv?: PvTotals | null;
    team?: { direct: number; waiting: number; size: { L: number; R: number } };
    lastMatch?: { day: string; matches: number; amount: number; capped: boolean; status: string } | null;
    autoship?: {
        bought: number;
        needed: number;
        reminder: { month: string; deadline: string; bought: number } | null;
        missed: number;
        flushAfter: number;
    } | null;
    leadership?: { pv: number; needed: number; last: { month: string; amount: number; status: string } | null };
    toCollect?: number;
    recent?: { id: number; at: string; amount: number; wallet: string; memo: string | null }[];
    notices?: Notice[];
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

function Notice({ children, href, action }: { children: React.ReactNode; href: string; action: string }) {
    return (
        <div className="bg-accent text-accent-foreground flex flex-wrap items-center justify-between gap-3 rounded-xl p-4">
            <p className="text-sm">{children}</p>
            <Button asChild size="sm">
                <Link href={href}>{action}</Link>
            </Button>
        </div>
    );
}

function ReferralLink({ link }: { link: string }) {
    const [copied, setCopied] = useState(false);

    return (
        <div className="flex min-w-0 flex-wrap items-center gap-2 text-sm">
            <span className="text-muted-foreground">Your sign-up link:</span>
            <span className="bg-muted max-w-full truncate rounded px-2 py-1 font-mono text-xs">{link}</span>
            <button
                type="button"
                className="text-primary text-xs font-medium underline-offset-4 hover:underline"
                onClick={() => {
                    navigator.clipboard?.writeText(link);
                    setCopied(true);
                }}
            >
                {copied ? 'Copied' : 'Copy'}
            </button>
        </div>
    );
}

export default function Dashboard({
    member,
    money,
    pv: pvTotals,
    team,
    lastMatch,
    autoship,
    leadership,
    toCollect = 0,
    recent = [],
    notices = [],
}: Props) {
    if (!member || !money || !team) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Dashboard" />
                <div className="space-y-4 p-4">
                    <Notice href={route('package')} action="Choose your package">
                        <span className="font-medium">One step left:</span> pay your package to become a member and take your place in the tree.
                    </Notice>
                    <p className="text-muted-foreground text-sm">
                        Pay online, or with an e-pin a member gave you. Your dashboard fills in as soon as you are a member.
                    </p>
                </div>
            </AppLayout>
        );
    }

    const autoshipShort = autoship && autoship.bought < autoship.needed;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div className="min-w-0">
                        <h1 className="text-2xl font-bold">Welcome back, {member.first_name}</h1>
                        <p className="text-muted-foreground text-sm">
                            {member.package ? `${member.package.name} member (${member.package.pv} PV)` : 'Member'} since {member.joined}
                        </p>
                    </div>
                    <ReferralLink link={member.referral_link} />
                </div>

                <div className="space-y-3">
                    {toCollect > 0 && (
                        <Notice href={route('orders.index')} action="See my codes">
                            You have {toCollect} {toCollect === 1 ? 'order' : 'orders'} to collect from a stockist with a pickup code.
                        </Notice>
                    )}
                    {member.kyc !== 'verified' && (
                        <Notice href={route('kyc.edit')} action={member.kyc === 'pending' ? 'See my check' : 'Verify me'}>
                            {member.kyc === 'pending'
                                ? 'Your identity check is being reviewed. You can withdraw once it is approved.'
                                : 'Verify your identity (KYC) to be able to withdraw.'}
                        </Notice>
                    )}
                    {autoship?.reminder ? (
                        <Notice href={route('shop')} action="Shop">
                            <span className="font-medium">
                                Make your autoship for {autoship.reminder.month} by {autoship.reminder.deadline}.
                            </span>{' '}
                            You have bought {pv(autoship.reminder.bought)} of {pv(autoship.needed)} PV of products for that month. Until you make it,
                            20% of that month's matching bonus stays held.
                        </Notice>
                    ) : (
                        autoshipShort && (
                            <Notice href={route('shop')} action="Shop">
                                You have bought {pv(autoship.bought)} of {pv(autoship.needed)} PV of products this month. Until you reach{' '}
                                {pv(autoship.needed)} PV, 20% of your matching bonus is held, and released to you as soon as you do.
                            </Notice>
                        )
                    )}
                    {autoship && autoship.missed > 0 && (
                        <p className="bg-warning-soft text-warning rounded-xl p-4 text-sm">
                            You missed your autoship {autoship.missed === 1 ? 'last month' : `in each of the last ${autoship.missed} months`}. Your
                            held matching is released when you make an autoship again. If autoship is missed {autoship.flushAfter} months in a row,
                            the matching held for those months goes to the cooperative's reserve.
                        </p>
                    )}
                </div>

                <div className="grid gap-4 sm:grid-cols-2 2xl:grid-cols-4">
                    <StatCard label="Main balance" value={formatKobo(money.main)} icon={Banknote} tone="success" hint="Ready to withdraw or spend" />
                    <StatCard
                        label="In bonus wallets"
                        value={formatKobo(money.bonuses)}
                        icon={Gift}
                        tone="gold"
                        hint="Move to your main balance on the Wallet page"
                    />
                    <StatCard
                        label="Earned so far"
                        value={formatKobo(money.earned)}
                        icon={TrendingUp}
                        tone="info"
                        hint="Every bonus ever paid to you"
                    />
                    <StatCard label="VISA wallet" value={formatKobo(money.visa)} icon={PiggyBank} hint="For Add distributor and e-pins" />
                    {money.autoship > 0 && (
                        <StatCard
                            label="Held until autoship"
                            value={formatKobo(money.autoship)}
                            icon={ShoppingBasket}
                            hint="Released when you make your autoship (you have until the 7th of the next month); kept by the cooperative after 3 missed months in a row"
                        />
                    )}
                </div>

                {notices.length > 0 && (
                    <section className="space-y-2">
                        <div className="flex items-center justify-between">
                            <h2 className="font-medium">Notices</h2>
                            <Link href={route('notices')} className="text-primary text-sm font-medium underline-offset-4 hover:underline">
                                See all
                            </Link>
                        </div>
                        <NoticeList notices={notices} />
                    </section>
                )}

                <section className="space-y-2">
                    <h2 className="font-medium">Your PV</h2>
                    <PvSummary totals={pvTotals ?? null} />
                </section>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card className="py-0">
                        <CardContent className="space-y-3 p-5">
                            <h2 className="font-medium">Matching bonus</h2>
                            {member.package && (
                                <p className="text-muted-foreground text-sm">
                                    Each night, 50 PV on each leg makes a match: {formatKobo(member.package.per_match)} each, up to{' '}
                                    {member.package.cap} a day.
                                </p>
                            )}
                            {lastMatch ? (
                                <p className="text-sm">
                                    Last paid for <span className="font-medium">{lastMatch.day}</span>: {lastMatch.matches}{' '}
                                    {lastMatch.matches === 1 ? 'match' : 'matches'}, {formatKobo(lastMatch.amount)}{' '}
                                    {lastMatch.capped && <Badge variant="warning">Daily cap</Badge>}{' '}
                                    {lastMatch.status === 'held' && <Badge variant="warning">Held until the pool refills</Badge>}
                                </p>
                            ) : (
                                <p className="text-sm">No matches yet. They start once both legs have 50 PV waiting.</p>
                            )}
                            <Link href={route('team.pv')} className="text-primary text-sm font-medium underline-offset-4 hover:underline">
                                PV log and matching history
                            </Link>
                        </CardContent>
                    </Card>

                    <Card className="py-0">
                        <CardContent className="space-y-3 p-5">
                            <h2 className="font-medium">Your team</h2>
                            <dl className="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                                <div>
                                    <dt className="text-muted-foreground">You sponsored</dt>
                                    <dd className="text-xl font-semibold tabular-nums">{team.direct}</dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">Not paid yet</dt>
                                    <dd className="text-xl font-semibold tabular-nums">{team.waiting}</dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">Left team</dt>
                                    <dd className="text-xl font-semibold tabular-nums">{team.size.L}</dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">Right team</dt>
                                    <dd className="text-xl font-semibold tabular-nums">{team.size.R}</dd>
                                </div>
                            </dl>
                            <div className="flex flex-wrap gap-2">
                                <Button asChild size="sm" variant="secondary">
                                    <Link href={route('team')}>See my tree</Link>
                                </Button>
                                <Button asChild size="sm" variant="secondary">
                                    <Link href={route('team.add')}>Add distributor</Link>
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {leadership && (
                    <Card className="py-0">
                        <CardContent className="space-y-3 p-5">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <h2 className="font-medium">Leadership bonus</h2>
                                {leadership.pv >= leadership.needed && <Badge variant="gold">You qualify</Badge>}
                            </div>
                            <p className="text-muted-foreground text-sm">
                                Leaders earn 5% of what their team earns from matching each month. You qualify at {pv(leadership.needed)} PV on your
                                lesser leg.
                            </p>
                            <div>
                                <div className="flex justify-between text-sm">
                                    <span>Your lesser leg</span>
                                    <span className="tabular-nums">
                                        {pv(leadership.pv)} / {pv(leadership.needed)} PV
                                    </span>
                                </div>
                                <div
                                    className="bg-muted mt-1 h-2 overflow-hidden rounded-full"
                                    role="progressbar"
                                    aria-label="Lesser leg PV towards the leadership bonus"
                                    aria-valuemin={0}
                                    aria-valuemax={leadership.needed}
                                    aria-valuenow={Math.min(leadership.pv, leadership.needed)}
                                >
                                    <div
                                        className="bg-primary h-full"
                                        style={{ width: `${Math.min(100, (leadership.pv / leadership.needed) * 100)}%` }}
                                    />
                                </div>
                            </div>
                            {leadership.last && (
                                <p className="text-sm">
                                    {leadership.last.month}:{' '}
                                    {leadership.last.amount > 0
                                        ? formatKobo(leadership.last.amount)
                                        : 'nothing this time (your team earned no matching)'}{' '}
                                    {leadership.last.status === 'held' && <Badge variant="warning">Held until the pool refills</Badge>}
                                </p>
                            )}
                        </CardContent>
                    </Card>
                )}

                <section className="space-y-2">
                    <div className="flex items-center justify-between">
                        <h2 className="font-medium">Latest earnings</h2>
                        <Link href={route('wallet')} className="text-primary text-sm font-medium underline-offset-4 hover:underline">
                            Wallet
                        </Link>
                    </div>
                    {recent.length === 0 ? (
                        <p className="text-muted-foreground text-sm">Your bonuses will appear here as you earn them.</p>
                    ) : (
                        <ul className="bg-card shadow-soft divide-y rounded-xl border">
                            {recent.map((r) => (
                                <li key={r.id} className="flex items-start justify-between gap-4 px-4 py-3 text-sm">
                                    <div className="min-w-0">
                                        <p className="font-medium">{r.wallet}</p>
                                        <p className="text-muted-foreground truncate text-xs">
                                            {r.at} · {r.memo}
                                        </p>
                                    </div>
                                    <span className="text-success shrink-0 font-medium tabular-nums">+{formatKobo(r.amount)}</span>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </AppLayout>
    );
}
