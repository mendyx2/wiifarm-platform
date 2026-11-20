import { Button } from '@/components/ui/button';
import { Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const KEY = 'wiifarm-cookies-ok';

/** The cookie notice (specification section 12), until the visitor says OK. */
export function CookieNotice() {
    const [show, setShow] = useState(false);
    useEffect(() => {
        try {
            setShow(localStorage.getItem(KEY) !== '1');
        } catch {
            setShow(true);
        }
    }, []);
    if (!show) return null;

    return (
        <div
            role="region"
            aria-label="Cookies"
            className="bg-card shadow-soft fixed inset-x-3 bottom-3 z-50 mx-auto flex max-w-2xl flex-wrap items-center gap-3 rounded-xl border p-4 text-sm"
        >
            <p className="min-w-0 flex-1">
                This site uses cookies to keep you signed in and to remember your cart. See our{' '}
                <Link href={route('page.privacy')} className="underline underline-offset-4">
                    privacy policy
                </Link>
                .
            </p>
            <Button
                size="sm"
                onClick={() => {
                    try {
                        localStorage.setItem(KEY, '1');
                    } catch {
                        /* private browsing: just hide it for now */
                    }
                    setShow(false);
                }}
            >
                OK
            </Button>
        </div>
    );
}
