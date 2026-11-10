import { Button } from '@/components/ui/button';
import { BinaryTree } from '@/components/wf/binary-tree';
import AdminLayout from '@/layouts/admin-layout';
import { type TreeNode } from '@/lib/network';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowUp } from 'lucide-react';

interface Props {
    member: { id: number; username: string; name: string };
    tree: TreeNode;
    up: number | null;
}

export default function AdminMemberTree({ member, tree, up }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/admin' },
        { title: 'Members', href: '/admin/members' },
        { title: `@${member.username}`, href: `/admin/members/${member.id}` },
        { title: 'Tree', href: `/admin/members/${member.id}/tree` },
    ];

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={`Tree: @${member.username}`} />
            <div className="space-y-4 p-4">
                <div className="flex flex-wrap items-center gap-2">
                    <p className="text-muted-foreground mr-auto text-sm">The team below @{member.username}. Click a member to move down.</p>
                    {up && (
                        <Button asChild variant="secondary" size="sm">
                            <Link href={route('admin.members.tree', up)}>
                                <ArrowUp className="size-4" />
                                One level up
                            </Link>
                        </Button>
                    )}
                    <Button asChild variant="ghost" size="sm">
                        <Link href={route('admin.members.show', member.id)}>Member details</Link>
                    </Button>
                </div>
                <BinaryTree root={tree} hrefFor={(id) => route('admin.members.tree', id)} highlight={member.id} />
            </div>
        </AdminLayout>
    );
}
