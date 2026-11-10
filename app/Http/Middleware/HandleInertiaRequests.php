<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        return array_merge(parent::share($request), [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                // Always the member (the web guard). On admin pages the admin
                // guard is the default, and an admin is never shared as a member.
                'user' => $request->is('admin', 'admin/*') ? null : Auth::guard('web')->user(),
            ],
            // On admin pages: the signed-in admin and what their roles allow,
            // so the panel only shows what they may use.
            // Named so no page's own "admin" prop (e.g. the admin being edited) can hide it.
            'signedInAdmin' => fn () => $this->admin($request),
            // A one-line message after an action, e.g. "Details saved."
            'flash' => ['status' => fn () => $request->session()->get('status')],
        ]);
    }

    /** @return array<string, mixed>|null */
    private function admin(Request $request): ?array
    {
        if (! $request->is('admin', 'admin/*')) {
            return null;
        }

        $admin = Auth::guard('admin')->user();

        if (! $admin instanceof Admin) {
            return null;
        }

        return [
            'id' => $admin->id,
            'name' => $admin->name,
            'username' => $admin->username,
            'email' => $admin->email,
            'isSuper' => $admin->isSuper(),
            'permissions' => $admin->permissions(),
        ];
    }
}
