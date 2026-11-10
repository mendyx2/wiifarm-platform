<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Services\Admin\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin sign-in with a username and password, separate from members
 * (specification section 11). Five wrong tries lock that username, from that
 * IP address, for a minute.
 */
class AdminSessionController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    public function create(): Response
    {
        return Inertia::render('admin/auth/login');
    }

    public function store(Request $request, Audit $audit): RedirectResponse
    {
        $request->validate([
            'username' => ['required', 'string', 'max:40'],
            'password' => ['required', 'string'],
        ]);

        $key = 'admin-login|'.Str::lower($request->string('username')).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'username' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($key), 'minutes' => ceil(RateLimiter::availableIn($key) / 60)]),
            ]);
        }

        $credentials = ['username' => $request->string('username')->trim()->toString(), 'password' => $request->string('password')->toString()];

        if (! Auth::guard('admin')->attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($key);

            throw ValidationException::withMessages(['username' => __('auth.failed')]);
        }

        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();

        if (! $admin->isActive()) {
            Auth::guard('admin')->logout();

            throw ValidationException::withMessages(['username' => 'This admin account has been deactivated.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        $admin->forceFill(['last_sign_in_at' => now()])->save();
        $audit->record($admin, 'admin.signed_in', $admin);

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();
        $request->session()->regenerateToken();

        return to_route('admin.login');
    }
}
