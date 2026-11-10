<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * An admin who is deactivated while signed in is signed out on their next
 * request (specification section 11).
 */
class AdminIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = Auth::guard('admin')->user();

        if ($admin instanceof Admin && ! $admin->isActive()) {
            Auth::guard('admin')->logout();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')->withErrors(['username' => 'This admin account has been deactivated.']);
        }

        return $next($request);
    }
}
