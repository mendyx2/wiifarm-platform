import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { AwardStatus, type AwardRow } from '@/components/wf/award-status';
import { DataTable } from '@/components/wf/data-table';
import AppLayout from '@/layouts/app-layout';
import { formatKobo } from '@/lib/money';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { Check } from 'lucide-react';

type Rank = { code: string; name: string; pv: number; cash: number; gift: string | null; package: string; needs: string | null; earned: boolean };

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Awards', href: '/awards' }];
const n = (v: number) => new Intl.NumberFormat('en-NG').format(v);

function Progress({ value, max, label }: { value: number; max: number; label: string }) {
    return (
        <div>
            <div className="flex justify-between text-sm">
                <span>{label}</span>
                <span className="tabular-nums">
                    {n(value)} / {n(max)} PV
                </span>
            </div>
            <div
                className="bg-muted mt-1 h-2 overflow-hidden rounded-full"
                role="progressbar"
                aria-label={label}
                aria-valuemin={0}
                aria-valuemax={max}
                aria-valuenow={Math.min(value, max)}
            >
                <div className="bg-primary h-full" style={{ width: `${Math.min(100, (value / max) * 100)}%` }} />
            </div>
        </div>
    );
}

export default function Awards({
    lesserLeg,
    package: pkg,
    ranks,
    teamRepurchase,
    tiers,
    awards,
}: {
    lesserLeg: number;
    package: string | null;
    ranks: Rank[];
    teamRepurchase: number;
    tiers: { pv: number; cash: number }[];
    awards: AwardRow[];
}) {
    const { errors } = usePage().props as { errors: Record<string, string> };
    const nextRank = ranks.find((r) => !r.earned);
    const nextTier = tiers.find((t) => t.pv > teamRepurchase);
    const choose = (a: AwardRow, choice: 'cash' | 'gift') => router.post(route('awards.choose', a.id), { choice }, { preserveScroll: true });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Awards" />
            <div className="max-w-5xl space-y-6 p-4">
                <section className="bg-card shadow-soft space-y-4 rounded-xl border p-4">
                    <h2 className="font-medium">Your progress</h2>
                    {nextRank && <Progress value={lesserLeg} max={nextRank.pv} label={`Lesser leg towards ${nextRank.name}`} />}
                    {nextTier && <Progress value={teamRepurchase} max={nextTier.pv} label="Your 1st to 10th generations' repurchases" />}
                    <p className="text-muted-foreground text-xs">Checked every night. An award waits for an admin's approval before it is paid.</p>
                </section>

                {awards.length > 0 && (
                    <section className="space-y-2">
                        <h2 className="font-medium">Your awards</h2>
                        <InputError message={errors.choice} />
                        <DataTable
                            rows={awards}
                            rowKey={(a) => a.id}
                            columns={[
                                { key: 'at', header: 'Qualified', cell: (a) => a.at, className: 'whitespace-nowrap' },
                                { key: 'name', header: 'Award', cell: (a) => <span className="font-medium">{a.name}</span> },
                                {
                                    key: 'value',
                                    header: 'Cash or gift',
                                    cell: (a) =>
                                        a.status === 'pending' && a.gift ? (
                                            <div className="flex flex-wrap gap-2">
                                                <Button
                                                    size="sm"
                                                    variant={a.choice === 'cash' ? 'default' : 'secondary'}
                                                    onClick={() => choose(a, 'cash')}
                                                >
                                                    {formatKobo(a.amount)}
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant={a.choice === 'gift' ? 'default' : 'secondary'}
                                                    onClick={() => choose(a, 'gift')}
                                                >
                                                    {a.gift}
                                                </Button>
                                            </div>
                                        ) : a.choice === 'gift' && a.gift ? (
                                            a.gift
                                        ) : (
                                            formatKobo(a.amount)
                                        ),
                                },
                                {
                                    key: 'status',
                                    header: 'Status',
                                    cell: (a) => (
                                        <>
                                            <AwardStatus status={a.status} />
                                            {a.reason && <span className="text-muted-foreground block text-xs">{a.reason}</span>}
                                        </>
                                    ),
                                },
                            ]}
                        />
                    </section>
                )}

                <section className="space-y-2">
                    <h2 className="font-medium">Recognition ranks</h2>
                    <p className="text-muted-foreground text-sm">
                        Earned once each, when the lifetime PV on your lesser leg reaches the level, on the package shown or higher
                        {pkg && ` (you are on ${pkg})`}. From Gold up you must also have produced the rank below on both your left and your right.
                        When you earn one, your first three uplines receive 5%, 2% and 1% of it.
                    </p>
                    <DataTable
                        rows={ranks}
                        rowKey={(r) => r.code}
                        columns={[
                            {
                                key: 'name',
                                header: 'Rank',
                                cell: (r) => (
                                    <span className="inline-flex items-center gap-2 font-medium">
                                        {r.earned && <Check className="text-success size-4" aria-label="Earned" />}
                                        {r.name}
                                    </span>
                                ),
                            },
                            { key: 'pv', header: 'Lesser leg', align: 'right', cell: (r) => `${n(r.pv)} PV`, className: 'whitespace-nowrap' },
                            {
                                key: 'award',
                                header: 'Award',
                                cell: (r) => (
                                    <>
                                        {formatKobo(r.cash)}
                                        {r.gift && <span className="text-muted-foreground block text-xs">or {r.gift}</span>}
                                    </>
                                ),
                            },
                            {
                                key: 'needs',
                                header: 'Also needs',
                                cell: (r) => (
                                    <span className="text-xs">
                                        <Badge variant="secondary">{r.package}</Badge>{' '}
                                        {r.needs && `and ${/^[AEIOU]/.test(r.needs) ? 'an' : 'a'} ${r.needs} on each side`}
                                    </span>
                                ),
                            },
                        ]}
                    />
                </section>

                <section className="space-y-2">
                    <h2 className="font-medium">Repurchase awards</h2>
                    <p className="text-muted-foreground text-sm">
                        On the total repurchases of your 1st to 10th generations. Each new level pays the difference from the last one you were paid.
                    </p>
                    <DataTable
                        rows={tiers}
                        rowKey={(t) => t.pv}
                        columns={[
                            { key: 'pv', header: 'Repurchase PV', align: 'right', cell: (t) => n(t.pv), className: 'whitespace-nowrap' },
                            {
                                key: 'cash',
                                header: 'Award (in total)',
                                align: 'right',
                                cell: (t) => formatKobo(t.cash),
                                className: 'whitespace-nowrap',
                            },
                            {
                                key: 'done',
                                header: '',
                                cell: (t) => (teamRepurchase >= t.pv ? <Check className="text-success size-4" aria-label="Reached" /> : null),
                            },
                        ]}
                    />
                </section>
            </div>
        </AppLayout>
    );
}
