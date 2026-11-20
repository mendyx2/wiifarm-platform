import { Button } from '@/components/ui/button';
import { ProductImage } from '@/components/wf/product-image';
import { RichText } from '@/components/wf/rich-text';
import PublicLayout from '@/layouts/public-layout';
import { formatKobo } from '@/lib/money';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';

type Page = { title: string; intro: string | null; body: string | null; image: string | null };
type Product = { id: number; name: string; price: number; image: string | null };

/** The home page, as the admins wrote it (specification section 12), with a few products from the shop. */
export default function Home({ page, products }: { page: Page; products: Product[] }) {
    const { auth } = usePage<SharedData>().props;

    return (
        <PublicLayout wide>
            <Head title={page.title} />
            <section className="grid items-center gap-8 py-4 md:grid-cols-2">
                <div className="space-y-5">
                    <h1 className="text-3xl font-bold tracking-tight sm:text-4xl">{page.title}</h1>
                    {page.intro && <p className="text-muted-foreground text-lg">{page.intro}</p>}
                    <div className="flex flex-wrap gap-3">
                        {auth.user ? (
                            <Button asChild size="lg">
                                <Link href={route('dashboard')}>Go to my dashboard</Link>
                            </Button>
                        ) : (
                            <Button asChild size="lg">
                                <Link href={route('register')}>Join Wiifarm</Link>
                            </Button>
                        )}
                        <Button asChild size="lg" variant="secondary">
                            <Link href={route('public.shop')}>Shop</Link>
                        </Button>
                    </div>
                </div>
                {page.image && <img src={page.image} alt="" className="shadow-soft aspect-[4/3] w-full rounded-2xl object-cover" />}
            </section>

            {page.body && <RichText text={page.body} className="max-w-3xl py-6" />}

            {products.length > 0 && (
                <section className="space-y-4 py-6">
                    <div className="flex items-center justify-between">
                        <h2 className="text-xl font-semibold">From our shop</h2>
                        <Link href={route('public.shop')} className="text-primary text-sm font-medium underline-offset-4 hover:underline">
                            See all
                        </Link>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-3">
                        {products.map((p) => (
                            <Link key={p.id} href={route('public.shop')} className="bg-card shadow-soft group overflow-hidden rounded-xl border">
                                <ProductImage src={p.image} name={p.name} className="aspect-square w-full" />
                                <div className="flex items-center justify-between gap-2 p-3 text-sm">
                                    <span className="font-medium group-hover:underline">{p.name}</span>
                                    <span className="tabular-nums">{formatKobo(p.price)}</span>
                                </div>
                            </Link>
                        ))}
                    </div>
                </section>
            )}
        </PublicLayout>
    );
}
