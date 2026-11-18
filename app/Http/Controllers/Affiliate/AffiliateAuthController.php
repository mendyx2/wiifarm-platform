<?php

namespace App\Http\Controllers\Affiliate;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Affiliates register and sign in on their own pages, separate from members (specification section 8). */
class AffiliateAuthController extends Controller
{
    public function showRegister(): Response
    {
        return Inertia::render('affiliate/register');
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:affiliates,email'],
            'phone' => ['required', 'string', 'regex:/^\+?[0-9 ]{7,20}$/'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'terms' => ['accepted'],
        ], ['terms.accepted' => 'Please accept the affiliate terms to continue.', 'phone.regex' => 'Enter a phone number in digits.']);

        $affiliate = new Affiliate($data);
        $affiliate->code = Affiliate::newCode();
        $affiliate->save();

        Auth::guard('affiliate')->login($affiliate);
        $request->session()->regenerate();

        return to_route('affiliate.dashboard')->with('status', "Welcome! Your referral code is {$affiliate->code}.");
    }

    public function showLogin(): Response
    {
        return Inertia::render('affiliate/login');
    }

    public function login(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $key = 'affiliate-login|'.Str::lower($request->string('email')).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($key), 'minutes' => ceil(RateLimiter::availableIn($key) / 60)])]);
        }
        if (! Auth::guard('affiliate')->attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            RateLimiter::hit($key);
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        /** @var Affiliate $affiliate */
        $affiliate = Auth::guard('affiliate')->user();
        if ($affiliate->isBanned()) {
            Auth::guard('affiliate')->logout();
            throw ValidationException::withMessages(['email' => 'This affiliate account has been banned'.($affiliate->ban_reason ? ': '.$affiliate->ban_reason : '.')]);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        return redirect()->intended(route('affiliate.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('affiliate')->logout();
        $request->session()->regenerateToken();

        return to_route('affiliate.login');
    }
}
