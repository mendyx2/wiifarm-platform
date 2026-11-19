import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';

/**
 * Temporary public home page for the new platform. The real public site
 * (banner, shop front, contact and policy pages) is built in a later phase
 * from specification section 12.
 */
export default function Welcome() {
    const { auth } = usePage<SharedData>().props;

    return (
        <>
            <Head title="Welcome" />
            <div className="bg-background text-foreground flex min-h-screen flex-col items-center justify-center p-6">
                <main className="w-full max-w-lg text-center">
                    <h1 className="mb-3 text-3xl font-semibold">Wiifarm Cooperative</h1>
                    <p className="text-muted-foreground mb-8 text-base">
                        Farm produce, a member network that rewards you, and savings that work for you.
                    </p>
                    <nav className="flex flex-wrap items-center justify-center gap-3">
                        <Link
                            href={route('public.shop')}
                            className="border-primary text-primary hover:bg-primary/10 rounded-md border px-5 py-2 text-sm font-medium"
                        >
                            Shop
                        </Link>
                        {auth.user ? (
                            <Link
                                href={route('dashboard')}
                                className="bg-primary text-primary-foreground hover:bg-primary/90 rounded-md px-5 py-2 text-sm font-medium"
                            >
                                Go to your dashboard
                            </Link>
                        ) : (
                            <>
                                <Link
                                    href={route('login')}
                                    className="border-primary text-primary hover:bg-primary/10 rounded-md border px-5 py-2 text-sm font-medium"
                                >
                                    Sign in
                                </Link>
                                <Link
                                    href={route('register')}
                                    className="bg-primary text-primary-foreground hover:bg-primary/90 rounded-md px-5 py-2 text-sm font-medium"
                                >
                                    Join Wiifarm
                                </Link>
                            </>
                        )}
                    </nav>
                </main>
            </div>
        </>
    );
}
