import AppLogoIcon from '@/components/app-logo-icon';
import { FlashMessage } from '@/components/flash-message';
import { Link } from '@inertiajs/react';
import { type ReactNode } from 'react';

/** The public site's frame: the shop, without the member sidebar. */
export default function PublicLayout({ children }: { children: ReactNode }) {
    return (
        <div className="bg-background text-foreground min-h-screen">
            <header className="border-b">
                <div className="mx-auto flex max-w-5xl items-center justify-between gap-4 px-4 py-3">
                    <Link href={route('public.shop')} className="flex items-center gap-2">
                        <span className="bg-primary text-primary-foreground flex size-8 items-center justify-center rounded-md">
                            <AppLogoIcon className="size-5 fill-current" />
                        </span>
                        <span className="font-semibold">Wiifarm</span>
                    </Link>
                    <nav className="flex items-center gap-4 text-sm">
                        <Link href={route('public.shop')} className="hover:underline">
                            Shop
                        </Link>
                        <Link href={route('login')} className="text-muted-foreground hover:underline">
                            Members
                        </Link>
                    </nav>
                </div>
            </header>
            <FlashMessage />
            <main className="mx-auto max-w-5xl px-4 py-6">{children}</main>
            <footer className="text-muted-foreground mx-auto max-w-5xl px-4 py-8 text-xs">
                Wiifarm Cooperative. Pay online; collect from any Wiifarm stockist with your pickup code.
            </footer>
        </div>
    );
}
