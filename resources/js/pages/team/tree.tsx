import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { BinaryTree } from '@/components/wf/binary-tree';
import { PvSummary } from '@/components/wf/pv-summary';
import AppLayout from '@/layouts/app-layout';
import { type PvTotals, type TreeNode } from '@/lib/network';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowUp, Clock, ScrollText } from 'lucide-react';

interface TreeProps {
    placed: boolean;
    tree: TreeNode | null;
    me: number;
    pv: PvTotals | null;
    up?: number | null;
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'My team', href: '/team' }];

export default function TeamTree({ placed, tree, me, pv, up }: TreeProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="My team" />
            <div className="space-y-6 p-4">
                {!placed || !tree ? (
                    <Alert variant="info">
                        <Clock className="size-4" />
                        <AlertTitle>You do not have a place in the team yet</AlertTitle>
                        <AlertDescription>You get your place, and start receiving PV, as soon as your package is paid.</AlertDescription>
                    </Alert>
                ) : (
                    <>
                        <PvSummary totals={pv} />
                        <div className="flex flex-wrap items-center gap-2">
                            {up && (
                                <Button asChild variant="secondary" size="sm">
                                    <Link href={up === me ? route('team') : route('team', { root: up })}>
                                        <ArrowUp className="size-4" />
                                        One level up
                                    </Link>
                                </Button>
                            )}
                            {tree.id !== me && (
                                <Button asChild variant="ghost" size="sm">
                                    <Link href={route('team')}>Back to me</Link>
                                </Button>
                            )}
                            <Button asChild variant="ghost" size="sm" className="ml-auto">
                                <Link href={route('team.pv')}>
                                    <ScrollText className="size-4" />
                                    My PV log
                                </Link>
                            </Button>
                        </div>
                        <BinaryTree root={tree} hrefFor={(id) => (id === me ? route('team') : route('team', { root: id }))} highlight={me} />
                        <p className="text-muted-foreground text-xs">
                            Under each member: the pairing PV waiting on their left (L) and right (R) legs. Click a member to see the team below them.
                        </p>
                    </>
                )}
            </div>
        </AppLayout>
    );
}
