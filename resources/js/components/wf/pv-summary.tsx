import { Card, CardContent } from '@/components/ui/card';
import { type PvTotals, emptyPv, pv } from '@/lib/network';

/** Lifetime and pairing PV on the left and right legs. */
export function PvSummary({ totals }: { totals: PvTotals | null }) {
    const t = totals ?? emptyPv;

    return (
        <div className="grid gap-4 sm:grid-cols-2">
            {(['L', 'R'] as const).map((leg) => (
                <Card key={leg} className="py-0">
                    <CardContent className="p-5">
                        <p className="font-medium">{leg === 'L' ? 'Left leg' : 'Right leg'}</p>
                        <dl className="mt-2 grid grid-cols-2 gap-2 text-sm">
                            <div>
                                <dt className="text-muted-foreground">Waiting to match</dt>
                                <dd className="text-2xl font-semibold tabular-nums">{pv(t[leg].pairing)} PV</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">Lifetime</dt>
                                <dd className="text-2xl font-semibold tabular-nums">{pv(t[leg].lifetime)} PV</dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>
            ))}
        </div>
    );
}
