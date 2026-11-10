import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';

/** Admin sign-in: separate from the member sign-in, by username only. */
export default function AdminLogin() {
    const { data, setData, post, processing, errors, reset } = useForm<{ username: string; password: string; remember: boolean }>({
        username: '',
        password: '',
        remember: false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('admin.login'), { onFinish: () => reset('password') });
    };

    return (
        <AuthLayout title="Wiifarm admin" description="Sign in with your admin username and password">
            <Head title="Admin sign-in" />

            <form className="grid gap-6" onSubmit={submit}>
                <div className="grid gap-2">
                    <Label htmlFor="username">Admin username</Label>
                    <Input
                        id="username"
                        required
                        autoFocus
                        autoComplete="username"
                        value={data.username}
                        onChange={(e) => setData('username', e.target.value)}
                    />
                    <InputError message={errors.username} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="password">Password</Label>
                    <Input
                        id="password"
                        type="password"
                        required
                        autoComplete="current-password"
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                    />
                    <InputError message={errors.password} />
                </div>

                <div className="flex items-center space-x-3">
                    <Checkbox id="remember" checked={data.remember} onCheckedChange={(v) => setData('remember', v === true)} />
                    <Label htmlFor="remember">Keep me signed in on this computer</Label>
                </div>

                <Button type="submit" className="w-full" disabled={processing}>
                    {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                    Sign in
                </Button>
            </form>
        </AuthLayout>
    );
}
