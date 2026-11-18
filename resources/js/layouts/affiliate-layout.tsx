import AppLogo from '@/components/app-logo';
import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { LogOut } from 'lucide-react';
import { type ReactNode } from 'react';

/** The affiliate area: a plain top bar, separate from the member and admin panels. */
export default function AffiliateLayout({ children }: { children: ReactNode }) {
    const { signedInAffiliate } = usePage<SharedData>().props;

    return (
        <div className="bg-background min-h-svh">
            <header className="bg-sidebar text-sidebar-foreground">
                <div className="mx-auto flex max-w-5xl items-center justify-between gap-3 px-4 py-3">
                    <Link href={route('affiliate.dashboard')} className="flex items-center gap-2">
                        <AppLogo />
                    </Link>
                    <div className="flex items-center gap-3 text-sm">
                        {signedInAffiliate && (
                            <span className="hidden sm:inline">
                                {signedInAffiliate.name} · {signedInAffiliate.code}
                            </span>
                        )}
                        <Button asChild size="sm" variant="secondary">
                            <Link href={route('affiliate.logout')} method="post" as="button">
                                <LogOut className="size-4" />
                                Sign out
                            </Link>
                        </Button>
                    </div>
                </div>
            </header>
            <main className="mx-auto max-w-5xl">
                <FlashMessage />
                {children}
            </main>
        </div>
    );
}
