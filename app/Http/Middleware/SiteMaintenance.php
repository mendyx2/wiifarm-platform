<?php

namespace App\Http\Middleware;

use App\Support\Settings;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Maintenance mode (specification section 12): switched on and off only by
 * Wiifarm's own admins, in Settings. The public site and the members' pages
 * show a maintenance page; the admin panel, payment confirmations and the
 * health check keep working, and a signed-in admin can still see the site.
 */
class SiteMaintenance
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->on()
            || $request->is('admin', 'admin/*', 'webhooks/*', 'up')
            || Auth::guard('admin')->check()) {
            return $next($request);
        }

        return Inertia::render('maintenance', ['message' => (string) Settings::get('site.maintenance_message')])
            ->toResponse($request)->setStatusCode(503)->header('Retry-After', '3600');
    }

    /** Off when the settings cannot be read yet (a fresh install before its database is ready). */
    private function on(): bool
    {
        try {
            return (bool) Settings::get('site.maintenance');
        } catch (QueryException) {
            return false;
        }
    }
}
