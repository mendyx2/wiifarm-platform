<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Network\SignUpChecks;
use App\Support\Settings;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Show the registration page.
     */
    public function create(): Response
    {
        return Inertia::render('auth/register', [
            'defaults' => [
                'dial_code' => config('wiifarm.default_dial_code'),
                'country' => config('wiifarm.default_country'),
            ],
            // A referral link (?ref=username) fills in the sponsor.
            'sponsor' => request()->string('ref')->toString() ?: null,
            'closed' => ! Settings::get('site.registration_open'),
        ]);
    }

    /**
     * Handle an incoming registration request (specification section 2).
     *
     * The account starts pending: the person becomes a member, and is placed
     * in the network, only when their package is paid (online or with an e-pin).
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        if (! Settings::get('site.registration_open')) {
            throw ValidationException::withMessages(['username' => 'Sign-ups are closed for now. Please try again later.']);
        }
        SignUpChecks::tidy($request);

        $request->validate(SignUpChecks::rules() + ['terms' => ['accepted']], SignUpChecks::messages());

        $checks = app(SignUpChecks::class);
        $sponsor = $checks->sponsorFor($request->input('sponsor'));
        $parent = $checks->parentFor($request->input('parent'), $request->input('leg'), $sponsor);

        $user = new User($request->only('first_name', 'last_name', 'username', 'email', 'dial_code', 'mobile', 'password'));
        $user->country = strtoupper($request->input('country'));
        $user->sponsor_id = $sponsor->id;
        // The place asked for is used when the package is paid (specification section 3).
        $user->requested_parent_id = $parent?->id;
        $user->requested_leg = $request->input('leg');
        $user->save();

        event(new Registered($user));

        // The confirmation email goes out when the admin requires it.
        if (Settings::get('verification.email_required')) {
            $user->sendEmailVerificationNotification();
        }

        Auth::login($user);

        return to_route('dashboard');
    }
}
