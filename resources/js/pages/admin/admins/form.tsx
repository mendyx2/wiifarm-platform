import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AdminLayout from '@/layouts/admin-layout';
import { type AdminData, type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { UserX } from 'lucide-react';
import { FormEventHandler } from 'react';

type RoleOption = { id: number; name: string; description: string | null; is_super: boolean };
type Editing = { id: number; name: string; username: string; email: string; is_active: boolean; roles: number[]; last_sign_in_at: string | null };

function Standing({ admin }: { admin: Editing }) {
    const me = usePage<SharedData>().props.signedInAdmin as AdminData;
    const form = useForm({ reason: '' });
    const self = me.id === admin.id;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route(admin.is_active ? 'admin.admins.deactivate' : 'admin.admins.activate', admin.id), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">{admin.is_active ? 'Deactivate this admin' : 'Reactivate this admin'}</CardTitle>
                <CardDescription>
                    {admin.is_active
                        ? 'They are signed out on their next click and cannot sign in again until reactivated.'
                        : 'They can sign in again with their old password.'}
                </CardDescription>
            </CardHeader>
            <CardContent>
                {self ? (
                    <p className="text-muted-foreground text-sm">You cannot deactivate your own account.</p>
                ) : (
                    <form onSubmit={submit} className="grid gap-2">
                        <Label htmlFor="standing-reason">Reason</Label>
                        <Textarea id="standing-reason" rows={2} value={form.data.reason} onChange={(e) => form.setData('reason', e.target.value)} />
                        <InputError message={form.errors.reason} />
                        <Button
                            className="w-fit"
                            variant={admin.is_active ? 'destructive' : 'secondary'}
                            disabled={form.processing || form.data.reason.trim() === ''}
                        >
                            {admin.is_active ? 'Deactivate' : 'Reactivate'}
                        </Button>
                    </form>
                )}
            </CardContent>
        </Card>
    );
}

export default function AdminForm({ admin, roles }: { admin: Editing | null; roles: RoleOption[] }) {
    const isNew = admin === null;
    const form = useForm({
        name: admin?.name ?? '',
        username: admin?.username ?? '',
        email: admin?.email ?? '',
        password: '',
        password_confirmation: '',
        roles: admin?.roles ?? ([] as number[]),
    });
    const errors = form.errors as Record<string, string>;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/admin' },
        { title: 'Admins', href: '/admin/admins' },
        { title: isNew ? 'Add an admin' : `@${admin.username}`, href: isNew ? '/admin/admins/create' : `/admin/admins/${admin.id}` },
    ];

    const toggleRole = (id: number, on: boolean) => form.setData('roles', on ? [...form.data.roles, id] : form.data.roles.filter((r) => r !== id));

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const done = { preserveScroll: true, onSuccess: () => form.reset('password', 'password_confirmation') };
        if (isNew) form.post(route('admin.admins.store'), done);
        else form.put(route('admin.admins.update', admin.id), done);
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={isNew ? 'Add an admin' : `Admin @${admin.username}`} />
            <div className="grid max-w-5xl gap-6 p-4 lg:grid-cols-[3fr_2fr]">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">{isNew ? 'Add an admin' : admin.name}</CardTitle>
                        {!isNew && (
                            <CardDescription>
                                @{admin.username} · last signed in {admin.last_sign_in_at ?? 'never'}{' '}
                                {!admin.is_active && (
                                    <Badge variant="danger" className="ml-1">
                                        Deactivated
                                    </Badge>
                                )}
                            </CardDescription>
                        )}
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="grid gap-4">
                            <div className="grid gap-1.5">
                                <Label htmlFor="name">Full name</Label>
                                <Input id="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                                <InputError message={form.errors.name} />
                            </div>
                            {isNew && (
                                <div className="grid gap-1.5">
                                    <Label htmlFor="username">Username (used to sign in; cannot be changed later)</Label>
                                    <Input id="username" value={form.data.username} onChange={(e) => form.setData('username', e.target.value)} />
                                    <InputError message={form.errors.username} />
                                </div>
                            )}
                            <div className="grid gap-1.5">
                                <Label htmlFor="email">Email address</Label>
                                <Input id="email" type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} />
                                <InputError message={form.errors.email} />
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-1.5">
                                    <Label htmlFor="password">{isNew ? 'Password' : 'New password (leave empty to keep)'}</Label>
                                    <Input
                                        id="password"
                                        type="password"
                                        autoComplete="new-password"
                                        value={form.data.password}
                                        onChange={(e) => form.setData('password', e.target.value)}
                                    />
                                    <InputError message={form.errors.password} />
                                </div>
                                <div className="grid gap-1.5">
                                    <Label htmlFor="password_confirmation">Type it again</Label>
                                    <Input
                                        id="password_confirmation"
                                        type="password"
                                        autoComplete="new-password"
                                        value={form.data.password_confirmation}
                                        onChange={(e) => form.setData('password_confirmation', e.target.value)}
                                    />
                                </div>
                            </div>
                            <p className="text-muted-foreground -mt-2 text-xs">At least 12 characters.</p>

                            <fieldset className="grid gap-3">
                                <legend className="mb-2 text-sm font-medium">Roles</legend>
                                {roles.map((r) => (
                                    <div key={r.id} className="flex items-start gap-3">
                                        <Checkbox
                                            id={`role-${r.id}`}
                                            className="mt-0.5"
                                            checked={form.data.roles.includes(r.id)}
                                            onCheckedChange={(v) => toggleRole(r.id, v === true)}
                                        />
                                        <div className="grid gap-0.5">
                                            <Label htmlFor={`role-${r.id}`}>{r.name}</Label>
                                            {r.description && <p className="text-muted-foreground text-xs">{r.description}</p>}
                                        </div>
                                    </div>
                                ))}
                                <InputError message={errors.roles ?? errors['roles.0']} />
                                <Link href={route('admin.roles.index')} className="text-muted-foreground text-xs underline underline-offset-4">
                                    See what each role allows
                                </Link>
                            </fieldset>

                            <Button className="w-fit" disabled={form.processing || form.data.roles.length === 0}>
                                {isNew ? 'Add admin' : 'Save'}
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <div className="space-y-6">
                    {isNew ? (
                        <Alert variant="info">
                            <UserX className="size-4" />
                            <AlertTitle>Give the password in person</AlertTitle>
                            <AlertDescription>
                                Do not send it by email or chat. Ask the new admin to turn on two-factor sign-in when it arrives.
                            </AlertDescription>
                        </Alert>
                    ) : (
                        <Standing admin={admin} />
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}
