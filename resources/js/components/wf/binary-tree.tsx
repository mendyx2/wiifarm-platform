import { Badge } from '@/components/ui/badge';
import { type TreeNode, pv } from '@/lib/network';
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';

/**
 * A few levels of the binary tree: each member with their package and the
 * pairing PV waiting on each leg. Click a member to see the tree below them.
 * On a small screen it scrolls sideways inside its frame.
 */
export function BinaryTree({ root, hrefFor, highlight }: { root: TreeNode; hrefFor: (id: number) => string; highlight?: number }) {
    return (
        <div className="overflow-x-auto rounded-lg border p-4">
            <div className="min-w-[760px]">
                <Branch node={root} hrefFor={hrefFor} highlight={highlight} />
            </div>
        </div>
    );
}

function Branch({ node, hrefFor, highlight }: { node: TreeNode | null; hrefFor: (id: number) => string; highlight?: number }) {
    if (node === null) {
        return (
            <div className="flex justify-center">
                <div className="text-muted-foreground w-full max-w-28 rounded-md border border-dashed px-2 py-3 text-center text-xs">Free place</div>
            </div>
        );
    }

    return (
        <div className="flex flex-col items-center">
            <Member node={node} href={hrefFor(node.id)} highlight={node.id === highlight} />
            {node.children && (
                <div className="mt-3 grid w-full grid-cols-2 gap-2 border-t pt-3">
                    <Branch node={node.children.L} hrefFor={hrefFor} highlight={highlight} />
                    <Branch node={node.children.R} hrefFor={hrefFor} highlight={highlight} />
                </div>
            )}
        </div>
    );
}

function Member({ node, href, highlight }: { node: TreeNode; href: string; highlight: boolean }) {
    return (
        <Link
            href={href}
            className={cn(
                'bg-card hover:border-primary block w-full max-w-32 min-w-0 rounded-md border px-2 py-2 text-center text-xs shadow-xs transition-colors',
                highlight && 'border-primary ring-primary/30 ring-2',
                node.banned && 'opacity-60',
            )}
            title={`${node.name}: left ${pv(node.pv.L.pairing)} PV, right ${pv(node.pv.R.pairing)} PV waiting to match`}
        >
            <span className="block truncate font-medium">@{node.username}</span>
            <span className="text-muted-foreground block truncate">{node.name}</span>
            <span className="mt-1 flex flex-wrap justify-center gap-1">
                {node.package && (
                    <Badge variant="secondary" className="px-1.5 py-0 text-[10px]">
                        {node.package}
                    </Badge>
                )}
                {node.banned && (
                    <Badge variant="danger" className="px-1.5 py-0 text-[10px]">
                        Banned
                    </Badge>
                )}
            </span>
            <span className="mt-1 flex flex-wrap justify-between gap-x-1 tabular-nums">
                <span>L {pv(node.pv.L.pairing)}</span>
                <span>R {pv(node.pv.R.pairing)}</span>
            </span>
            {node.more_below && <ChevronDown className="text-muted-foreground mx-auto mt-0.5 size-3.5" aria-label="More members below" />}
        </Link>
    );
}
