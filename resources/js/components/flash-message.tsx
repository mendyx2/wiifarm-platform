import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';

/** The one-line message after an action, e.g. "Order WF0000011 is paid." */
export function FlashMessage() {
    const { flash } = usePage<SharedData>().props;
    if (!flash?.status) return null;

    return (
        <div role="status" className="bg-success-soft text-success mx-4 mt-4 flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium">
            <CheckCircle2 className="size-4 shrink-0" aria-hidden />
            {flash.status}
        </div>
    );
}
