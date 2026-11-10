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
            <div className="flex min-h-screen flex-col items-center justify-center bg-[#f6f8f4] p-6 text-[#1b2a1b] dark:bg-[#0f160f] dark:text-[#e8efe6]">
                <main className="w-full max-w-lg text-center">
                    <h1 className="mb-3 text-3xl font-semibold">Wiifarm Cooperative</h1>
                    <p className="mb-8 text-base text-[#4a5a48] dark:text-[#a9b8a6]">
                        Farm produce, a member network that rewards you, and savings that work for you.
                    </p>
                    <nav className="flex items-center justify-center gap-3">
                        {auth.user ? (
                            <Link
                                href={route('dashboard')}
                                className="rounded-md bg-[#2e7d32] px-5 py-2 text-sm font-medium text-white hover:bg-[#25692a]"
                            >
                                Go to your dashboard
                            </Link>
                        ) : (
                            <>
                                <Link
                                    href={route('login')}
                                    className="rounded-md border border-[#2e7d32] px-5 py-2 text-sm font-medium text-[#2e7d32] hover:bg-[#2e7d32]/10 dark:text-[#7fc283]"
                                >
                                    Sign in
                                </Link>
                                <Link
                                    href={route('register')}
                                    className="rounded-md bg-[#2e7d32] px-5 py-2 text-sm font-medium text-white hover:bg-[#25692a]"
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
