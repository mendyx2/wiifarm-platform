<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Settings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * When the admin has switched email or phone verification on, a member must
 * confirm them before using the member area (specification section 2).
 * Profile settings stay reachable, so a wrong email or number can be fixed.
 */
class RequireVerification
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User) {
            if (Settings::get('verification.email_required') && $user->email_verified_at === null) {
                return redirect()->route('verification.notice');
            }
            if (Settings::get('verification.mobile_required') && $user->mobile_verified_at === null) {
                return redirect()->route('mobile.verify');
            }
        }

        return $next($request);
    }
}
