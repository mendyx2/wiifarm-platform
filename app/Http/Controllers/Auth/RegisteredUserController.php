<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules;
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
        ]);
    }

    /**
     * Handle an incoming registration request (specification section 2).
     *
     * The account starts pending: the person becomes a member, and is placed
     * in the network, only when their package is paid (a later phase).
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'username' => trim((string) $request->input('username')),
            'sponsor' => trim((string) $request->input('sponsor')) ?: null,
        ]);

        $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'username' => ['required', 'string', User::USERNAME_RULE, 'unique:'.User::class],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'dial_code' => ['required', 'string', 'regex:/^[0-9]{1,4}$/'],
            'mobile' => ['required', 'string', 'regex:/^[0-9]{6,15}$/'],
            'country' => ['required', 'string', 'size:2', 'alpha'],
            'sponsor' => ['nullable', 'string', 'max:40'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'terms' => ['accepted'],
        ], [
            'username.regex' => 'Usernames are 6 to 40 letters, numbers or underscores, with no spaces.',
            'mobile.regex' => 'Enter the mobile number in digits only, without the country code.',
            'terms.accepted' => 'Please accept the member terms to continue.',
        ]);

        $sponsor = $this->sponsorFor($request->input('sponsor'));

        $user = new User($request->only('first_name', 'last_name', 'username', 'email', 'dial_code', 'mobile', 'password'));
        $user->country = strtoupper($request->input('country'));
        $user->sponsor_id = $sponsor->id;
        $user->save();

        event(new Registered($user));

        Auth::login($user);

        return to_route('dashboard');
    }

    /**
     * The sponsor named on the form, or the company account when it is left blank.
     * A sponsor must exist and must not be banned.
     *
     * @throws ValidationException
     */
    private function sponsorFor(?string $username): User
    {
        if ($username === null) {
            return User::company() ?? throw ValidationException::withMessages([
                'sponsor' => 'Sign-up is not open yet: the company account has not been set up.',
            ]);
        }

        $sponsor = User::where('username', $username)->first();

        if (! $sponsor) {
            throw ValidationException::withMessages(['sponsor' => 'There is no member with that username. Check it, or leave it blank.']);
        }
        if ($sponsor->isBanned()) {
            throw ValidationException::withMessages(['sponsor' => 'That member cannot sponsor new members. Check the username, or leave it blank.']);
        }

        return $sponsor;
    }
}
