<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Network\Tree;
use App\Support\Settings;
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
            'parent' => trim((string) $request->input('parent')) ?: null,
            'leg' => $request->input('leg') ?: null,
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
            'parent' => ['nullable', 'string', 'max:40'],
            'leg' => ['nullable', 'in:L,R'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'terms' => ['accepted'],
        ], [
            'username.regex' => 'Usernames are 6 to 40 letters, numbers or underscores, with no spaces.',
            'mobile.regex' => 'Enter the mobile number in digits only, without the country code.',
            'terms.accepted' => 'Please accept the member terms to continue.',
        ]);

        $sponsor = $this->sponsorFor($request->input('sponsor'));
        $parent = $this->parentFor($request->input('parent'), $request->input('leg'), $sponsor);

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

    /**
     * The parent asked for, if any: a member with a place in the sponsor's own
     * tree. A leg asked for must be free now; if it is taken by the time the
     * package is paid, the member goes down that same leg.
     *
     * @throws ValidationException
     */
    private function parentFor(?string $username, ?string $leg, User $sponsor): ?User
    {
        $tree = app(Tree::class);
        $parent = $username === null ? null : User::where('username', $username)->first();

        if ($username !== null && $parent === null) {
            throw ValidationException::withMessages(['parent' => 'There is no member with that username.']);
        }

        $under = $parent ?? $sponsor;
        if (! $under->position()->exists()) {
            if ($parent === null && $leg === null) {
                return null; // no choice made: nothing to check now
            }
            throw ValidationException::withMessages([$parent ? 'parent' : 'leg' => "@{$under->username} has no place in the team yet, so you cannot choose a place under them. Leave this blank."]);
        }
        if ($parent !== null && ! $tree->isInTreeOf($parent->id, $sponsor->id)) {
            throw ValidationException::withMessages(['parent' => "@{$parent->username} is not in your sponsor's team. Choose someone in it, or leave this blank."]);
        }
        if ($leg !== null && isset($tree->children([$under->id])[$under->id][$leg])) {
            throw ValidationException::withMessages(['leg' => 'That place is already taken. Choose the other side, or leave it as "No preference".']);
        }

        return $parent;
    }
}
