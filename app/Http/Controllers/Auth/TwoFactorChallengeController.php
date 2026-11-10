<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Members\TwoFactor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The second step of signing in, for members with two-factor on.
 */
class TwoFactorChallengeController extends Controller
{
    public function __construct(private readonly TwoFactor $twoFactor) {}

    public function show(Request $request): Response|RedirectResponse
    {
        if (! $request->session()->has('two_factor.user_id')) {
            return to_route('login');
        }

        return Inertia::render('auth/two-factor-challenge');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:20']]);

        $user = User::find($request->session()->get('two_factor.user_id'));

        if (! $user || $user->isBanned()) {
            $request->session()->forget(['two_factor.user_id', 'two_factor.remember']);

            return to_route('login');
        }

        if (! $this->twoFactor->check($user, $request->string('code')->toString())) {
            return back()->withErrors(['code' => 'That code is not right. Use the 6-digit code from your app, or one of your backup codes.']);
        }

        $remember = (bool) $request->session()->pull('two_factor.remember', false);
        $request->session()->forget('two_factor.user_id');

        Auth::guard('web')->login($user, $remember);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
