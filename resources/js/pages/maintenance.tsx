import AppLogoIcon from '@/components/app-logo-icon';
import { Head } from '@inertiajs/react';

/** Shown while Wiifarm's admins have switched maintenance mode on (specification section 12). */
export default function Maintenance({ message }: { message: string }) {
    return (
        <div className="bg-background text-foreground flex min-h-screen items-center justify-center p-6">
            <Head title="Back shortly" />
            <main className="max-w-md space-y-4 text-center">
                <span className="bg-primary text-primary-foreground mx-auto flex size-12 items-center justify-center rounded-xl">
                    <AppLogoIcon className="size-7 fill-current" />
                </span>
                <h1 className="text-2xl font-bold">Back shortly</h1>
                <p className="text-muted-foreground">{message}</p>
            </main>
        </div>
    );
}
