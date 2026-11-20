import AppLogoIcon from '@/components/app-logo-icon';
import { FlashMessage } from '@/components/flash-message';
import { CookieNotice } from '@/components/wf/cookie-notice';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { type ReactNode } from 'react';

/** The public website's frame: the header with its menu, the footer with the policy pages, and the cookie notice. */
export default function PublicLayout({ children, wide = false }: { children: ReactNode; wide?: boolean }) {
    const { auth } = usePage<SharedData>().props;
    const width = wide ? 'max-w-6xl' : 'max-w-5xl';

    return (
        <div className="bg-background text-foreground flex min-h-screen flex-col">
            <header className="border-b">
                <div className={`mx-auto flex ${width} flex-wrap items-center justify-between gap-x-4 gap-y-2 px-4 py-3`}>
                    <Link href={route('home')} className="flex items-center gap-2">
                        <span className="bg-primary text-primary-foreground flex size-8 items-center justify-center rounded-md">
                            <AppLogoIcon className="size-5 fill-current" />
                        </span>
                        <span className="font-semibold">Wiifarm</span>
                    </Link>
                    <nav className="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm" aria-label="Main">
                        <Link href={route('public.shop')} className="hover:underline">
                            Shop
                        </Link>
                        <Link href={route('page.about')} className="hover:underline">
                            About
                        </Link>
                        <Link href={route('page.contact')} className="hover:underline">
                            Contact
                        </Link>
                        {auth.user ? (
                            <Link href={route('dashboard')} className="text-primary font-medium hover:underline">
                                My dashboard
                            </Link>
                        ) : (
                            <>
                                <Link href={route('login')} className="text-muted-foreground hover:underline">
                                    Sign in
                                </Link>
                                <Link href={route('register')} className="bg-primary text-primary-foreground rounded-md px-3 py-1.5 font-medium">
                                    Join
                                </Link>
                            </>
                        )}
                    </nav>
                </div>
            </header>
            <FlashMessage />
            <main className={`mx-auto w-full ${width} flex-1 px-4 py-6`}>{children}</main>
            <footer className="border-t">
                <div className={`text-muted-foreground mx-auto flex ${width} flex-wrap items-center justify-between gap-3 px-4 py-6 text-xs`}>
                    <p>© {new Date().getFullYear()} Wiifarm Cooperative. Pay online; collect from any Wiifarm stockist with your pickup code.</p>
                    <nav className="flex gap-4" aria-label="Policies">
                        <Link href={route('page.privacy')} className="hover:underline">
                            Privacy
                        </Link>
                        <Link href={route('page.terms')} className="hover:underline">
                            Terms
                        </Link>
                        <Link href={route('page.contact')} className="hover:underline">
                            Contact
                        </Link>
                    </nav>
                </div>
            </footer>
            <CookieNotice />
        </div>
    );
}
