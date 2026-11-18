<?php

namespace App\Services\Network;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;

/**
 * The checks every sign-up form makes (specification section 2), whether the
 * person signs themselves up or a member keys them in ("Add Distributor").
 */
class SignUpChecks
{
    /** Tidies the form before it is checked. */
    public static function tidy(Request $request): void
    {
        $request->merge([
            'username' => trim((string) $request->input('username')),
            'sponsor' => trim((string) $request->input('sponsor')) ?: null,
            'parent' => trim((string) $request->input('parent')) ?: null,
            'leg' => $request->input('leg') ?: null,
        ]);
    }

    /**
     * The rules for the new person's details. A key-in also needs the full
     * address and a sponsor.
     *
     * @return array<string, mixed>
     */
    public static function rules(bool $keyIn = false): array
    {
        return [
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'username' => ['required', 'string', User::USERNAME_RULE, 'unique:'.User::class],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'dial_code' => ['required', 'string', 'regex:/^[0-9]{1,4}$/'],
            'mobile' => ['required', 'string', 'regex:/^[0-9]{6,15}$/'],
            'country' => ['required', 'string', 'size:2', 'alpha'],
            'state' => [$keyIn ? 'required' : 'nullable', 'string', 'max:80'],
            'city' => [$keyIn ? 'required' : 'nullable', 'string', 'max:80'],
            'address' => [$keyIn ? 'required' : 'nullable', 'string', 'max:255'],
            'sponsor' => [$keyIn ? 'required' : 'nullable', 'string', 'max:40'],
            'parent' => ['nullable', 'string', 'max:40'],
            'leg' => ['nullable', 'in:L,R'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'username.regex' => 'Usernames are 6 to 40 letters, numbers or underscores, with no spaces.',
            'mobile.regex' => 'Enter the mobile number in digits only, without the country code.',
            'terms.accepted' => 'Please accept the member terms to continue.',
        ];
    }

    /**
     * The sponsor named on the form, or the company account when it is left blank.
     * A sponsor must exist and must not be banned.
     *
     * @throws ValidationException
     */
    public function sponsorFor(?string $username): User
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
    public function parentFor(?string $username, ?string $leg, User $sponsor): ?User
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
