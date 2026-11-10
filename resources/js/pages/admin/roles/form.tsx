import InputError from '@/components/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/admin-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Lock } from 'lucide-react';
import { FormEventHandler } from 'react';

type Group = { name: string; permissions: { key: string; label: string }[] };
type Editing = {
    id: number;
    name: string;
    description: string | null;
    is_super: boolean;
    permissions: string[];
    admins: { id: number; name: string; username: string }[];
};

export default function RoleForm({ role, groups, superOnly }: { role: Editing | null; groups: Group[]; superOnly: string[] }) {
    const isNew = role === null;
    const locked = role?.is_super ?? false;
    const { errors: pageErrors } = usePage<{ errors: Record<string, string> }>().props;
    const form = useForm({ name: role?.name ?? '', description: role?.description ?? '', permissions: role?.permissions ?? ([] as string[]) });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/admin' },
        { title: 'Roles', href: '/admin/roles' },
        { title: isNew ? 'Create a role' : role.name, href: isNew ? '/admin/roles/create' : `/admin/roles/${role.id}` },
    ];

    const toggle = (key: string, on: boolean) =>
        form.setData('permissions', on ? [...form.data.permissions, key] : form.data.permissions.filter((p) => p !== key));

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (isNew) form.post(route('admin.roles.store'));
        else form.put(route('admin.roles.update', role.id), { preserveScroll: true, onSuccess: () => form.setDefaults() });
    };

    const remove = () => {
        if (role && window.confirm(`Delete the role "${role.name}"? This cannot be undone.`)) {
            router.delete(route('admin.roles.destroy', role.id));
        }
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={isNew ? 'Create a role' : `Role: ${role.name}`} />
            <form onSubmit={submit} className="max-w-4xl space-y-6 p-4">
                {locked && (
                    <Alert variant="info">
                        <Lock className="size-4" />
                        <AlertDescription>
                            The super-admin role always has every permission, including those only it may have. It cannot be changed.
                        </AlertDescription>
                    </Alert>
                )}

                <Card>
                    <CardContent className="grid gap-4 pt-6 sm:grid-cols-2">
                        <div className="grid gap-1.5">
                            <Label htmlFor="name">Role name</Label>
                            <Input
                                id="name"
                                disabled={locked}
                                value={form.data.name}
                                onChange={(e) => form.setData('name', e.target.value)}
                                placeholder="e.g. Support desk"
                            />
                            <InputError message={form.errors.name} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="description">What is it for? (optional)</Label>
                            <Input
                                id="description"
                                disabled={locked}
                                value={form.data.description}
                                onChange={(e) => form.setData('description', e.target.value)}
                            />
                            <InputError message={form.errors.description} />
                        </div>
                    </CardContent>
                </Card>

                <div className="grid gap-4 md:grid-cols-2">
                    {groups.map((g) => (
                        <Card key={g.name}>
                            <CardHeader className="pb-3">
                                <CardTitle className="text-base">{g.name}</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {g.permissions.map((p) => {
                                    const superOnlyPermission = superOnly.includes(p.key);
                                    return (
                                        <div key={p.key} className="flex items-start gap-3">
                                            <Checkbox
                                                id={p.key}
                                                className="mt-0.5"
                                                disabled={locked || superOnlyPermission}
                                                checked={locked || form.data.permissions.includes(p.key)}
                                                onCheckedChange={(v) => toggle(p.key, v === true)}
                                            />
                                            <div className="grid gap-0.5">
                                                <Label
                                                    htmlFor={p.key}
                                                    className={superOnlyPermission && !locked ? 'text-muted-foreground' : undefined}
                                                >
                                                    {p.label}
                                                </Label>
                                                {superOnlyPermission && !locked && <p className="text-muted-foreground text-xs">Super-admin only</p>}
                                            </div>
                                        </div>
                                    );
                                })}
                            </CardContent>
                        </Card>
                    ))}
                </div>
                <InputError message={form.errors.permissions} />

                {!locked && (
                    <div className="flex flex-wrap items-center gap-3">
                        <Button disabled={form.processing || (!isNew && !form.isDirty)}>{isNew ? 'Create role' : 'Save role'}</Button>
                        {!isNew && role.admins.length === 0 && (
                            <Button type="button" variant="ghost" className="text-destructive" onClick={remove}>
                                Delete this role
                            </Button>
                        )}
                        <InputError message={pageErrors.delete} />
                    </div>
                )}

                {!isNew && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Admins with this role</CardTitle>
                            {role.admins.length > 0 && !locked && <CardDescription>A role can only be deleted once no admin has it.</CardDescription>}
                        </CardHeader>
                        <CardContent className="flex flex-wrap gap-3 text-sm">
                            {role.admins.length === 0
                                ? 'None yet.'
                                : role.admins.map((a) => (
                                      <Link key={a.id} href={route('admin.admins.edit', a.id)} className="underline underline-offset-4">
                                          {a.name} (@{a.username})
                                      </Link>
                                  ))}
                        </CardContent>
                    </Card>
                )}
            </form>
        </AdminLayout>
    );
}
