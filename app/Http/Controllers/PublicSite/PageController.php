<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Content\SitePage;
use App\Models\Shop\Product;
use Inertia\Inertia;
use Inertia\Response;

/** The public website's pages, as the admins wrote them (specification section 12). */
class PageController extends Controller
{
    public function home(): Response
    {
        $page = SitePage::where('slug', 'home')->firstOrFail();

        return Inertia::render('public/home', [
            'page' => $page->toPublic(),
            'products' => Product::where('is_active', true)->orderBy('name')->limit(3)->get()
                ->map(fn (Product $p) => ['id' => $p->id, 'name' => $p->name, 'price' => $p->public_price, 'image' => $p->imageUrl()]),
        ])->withViewData(['meta' => $page->meta()]);
    }

    public function show(string $slug): Response
    {
        abort_unless(array_key_exists($slug, SitePage::SLUGS) && $slug !== 'home', 404);
        $page = SitePage::where('slug', $slug)->firstOrFail();

        return Inertia::render('public/page', ['page' => $page->toPublic()])->withViewData(['meta' => $page->meta()]);
    }
}
