import AppLogoIcon from '@/components/app-logo-icon';
import { cn } from '@/lib/utils';

/** A product's photo on a warm kraft backdrop, or a branded tile when it has none. */
export function ProductImage({ src, name, className }: { src: string | null; name: string; className?: string }) {
    return (
        <div className={cn('bg-secondary relative flex aspect-[4/3] items-center justify-center overflow-hidden rounded-lg', className)}>
            {src ? (
                <img src={src} alt={name} loading="lazy" className="h-full w-full object-contain p-3" />
            ) : (
                <div className="text-primary/70 flex flex-col items-center gap-2">
                    <AppLogoIcon className="size-10 fill-current" />
                    <span className="font-heading text-xs font-semibold tracking-wide uppercase">Wiifarm</span>
                </div>
            )}
        </div>
    );
}
