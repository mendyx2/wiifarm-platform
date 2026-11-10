<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets the request through only if the signed-in admin has the named
 * permission. Used on routes as  ->middleware('admin.can:members.ban').
 */
class AdminCan
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $admin = Auth::guard('admin')->user();

        abort_unless($admin instanceof Admin && $admin->hasPermission($permission), 403, 'Your admin role does not allow this.');

        return $next($request);
    }
}
