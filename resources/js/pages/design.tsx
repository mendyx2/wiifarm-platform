import AppLogo from '@/components/app-logo';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { DataTable, type Column } from '@/components/wf/data-table';
import { EmptyState } from '@/components/wf/empty-state';
import { StatCard } from '@/components/wf/stat-card';
import { formatKobo, formatPv } from '@/lib/money';
import { Head } from '@inertiajs/react';
import { CheckCircle2, Info, Network, TriangleAlert, Wallet } from 'lucide-react';

type Entry = { id: number; date: string; description: string; amount: number; status: 'paid' | 'pending' | 'rejected' };

const entries: Entry[] = [
    { id: 1, date: '10 Nov 2026', description: 'Direct bonus from a new member', amount: 6_750_000, status: 'paid' },
    { id: 2, date: '10 Nov 2026', description: 'Matching bonus, 8 matches', amount: 2_880_000, status: 'paid' },
    { id: 3, date: '9 Nov 2026', description: 'Withdrawal to bank', amount: -2_000_000, status: 'pending' },
    { id: 4, date: '8 Nov 2026', description: 'Withdrawal to bank', amount: -500_000, status: 'rejected' },
];

const statusBadge = { paid: 'success', pending: 'warning', rejected: 'danger' } as const;

const columns: Column<Entry>[] = [
    { key: 'date', header: 'Date', cell: (e) => e.date },
    { key: 'description', header: 'Description', cell: (e) => e.description },
    { key: 'status', header: 'Status', cell: (e) => <Badge variant={statusBadge[e.status]}>{e.status}</Badge> },
    {
        key: 'amount',
        header: 'Amount',
        align: 'right',
        cell: (e) => <span className={e.amount < 0 ? 'text-destructive' : ''}>{formatKobo(e.amount)}</span>,
    },
];

function Section({ title, children }: { title: string; children: React.ReactNode }) {
    return (
        <section className="space-y-3">
            <h2 className="text-muted-foreground text-sm font-semibold tracking-wide uppercase">{title}</h2>
            {children}
        </section>
    );
}

/** Design reference: every building block of the Wiifarm design system. Local only. */
export default function Design() {
    return (
        <>
            <Head title="Design reference" />
            <div className="mx-auto max-w-5xl space-y-10 p-6 md:p-10">
                <header className="flex items-center gap-2">
                    <AppLogo />
                    <span className="text-muted-foreground ml-auto text-sm">Design reference</span>
                </header>

                <Section title="Colours">
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        {[
                            ['Primary green', 'bg-primary'],
                            ['Harvest gold', 'bg-gold'],
                            ['Success', 'bg-success'],
                            ['Warning', 'bg-warning'],
                            ['Info', 'bg-info'],
                            ['Danger', 'bg-destructive'],
                            ['Sidebar', 'bg-sidebar'],
                            ['Muted', 'bg-muted'],
                        ].map(([name, cls]) => (
                            <div key={name} className="overflow-hidden rounded-lg border">
                                <div className={`h-14 ${cls}`} />
                                <p className="px-3 py-2 text-sm">{name}</p>
                            </div>
                        ))}
                    </div>
                </Section>

                <Section title="Buttons">
                    <div className="flex flex-wrap gap-3">
                        <Button>Approve withdrawal</Button>
                        <Button variant="secondary">Preview</Button>
                        <Button variant="outline">Cancel</Button>
                        <Button variant="destructive">Reject</Button>
                        <Button variant="ghost">More</Button>
                        <Button disabled>Processing…</Button>
                    </div>
                </Section>

                <Section title="Status badges">
                    <div className="flex flex-wrap gap-2">
                        <Badge variant="success">Paid</Badge>
                        <Badge variant="warning">Pending</Badge>
                        <Badge variant="danger">Rejected</Badge>
                        <Badge variant="info">Under review</Badge>
                        <Badge variant="gold">Mega</Badge>
                        <Badge variant="outline">Draft</Badge>
                    </div>
                </Section>

                <Section title="Headline figures">
                    <div className="grid gap-4 sm:grid-cols-3">
                        <StatCard label="Main balance" value={formatKobo(12_345_600)} icon={Wallet} tone="success" />
                        <StatCard label="Left leg" value={formatPv(1000)} hint="600 PV waiting to match" icon={Network} tone="info" />
                        <StatCard label="Package" value="Mega" hint="Up to 120 matches a day" icon={CheckCircle2} tone="gold" />
                    </div>
                </Section>

                <Section title="Messages">
                    <div className="grid gap-3">
                        <Alert variant="success">
                            <CheckCircle2 className="size-4" />
                            <AlertTitle>Withdrawal approved</AlertTitle>
                            <AlertDescription>₦19,900.00 will reach the member's bank account within 24 hours.</AlertDescription>
                        </Alert>
                        <Alert variant="warning">
                            <TriangleAlert className="size-4" />
                            <AlertTitle>Daily cap reached</AlertTitle>
                            <AlertDescription>Extra PV carries forward to tomorrow's matching run.</AlertDescription>
                        </Alert>
                        <Alert variant="info">
                            <Info className="size-4" />
                            <AlertTitle>KYC under review</AlertTitle>
                            <AlertDescription>An admin will check your documents shortly.</AlertDescription>
                        </Alert>
                    </div>
                </Section>

                <Section title="Form">
                    <Card>
                        <CardHeader>
                            <CardTitle>Withdraw to bank</CardTitle>
                            <CardDescription>Minimum ₦1,000. A ₦100 charge applies.</CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:max-w-sm">
                            <div className="grid gap-2">
                                <Label htmlFor="amount">Amount (₦)</Label>
                                <Input id="amount" inputMode="decimal" placeholder="20,000" />
                            </div>
                            <Button className="w-fit">Continue</Button>
                        </CardContent>
                    </Card>
                </Section>

                <Section title="Table">
                    <DataTable columns={columns} rows={entries} rowKey={(e) => e.id} />
                </Section>

                <Section title="Empty list">
                    <EmptyState title="No withdrawals yet" description="When you request a withdrawal, it appears here with its status." />
                </Section>
            </div>
        </>
    );
}
