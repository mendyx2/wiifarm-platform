<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\Members\TwoFactor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Turning two-factor sign-in on and off (settings > Two-factor).
 */
class TwoFactorController extends Controller
{
    public function __construct(private readonly TwoFactor $twoFactor) {}

    public function edit(Request $request): Response
    {
        $user = $request->user();
        $on = $this->twoFactor->isOn($user);
        $started = ! $on && $user->two_factor_secret !== null;

        return Inertia::render('settings/two-factor', [
            'on' => $on,
            'started' => $started,
            'qr' => $started ? $this->twoFactor->qrSvg($user) : null,
            'secret' => $started ? $this->twoFactor->secret($user) : null,
            'backupCodesLeft' => $this->twoFactor->backupCodesLeft($user),
            // Shown once, straight after they are made.
            'backupCodes' => $request->session()->get('two_factor.new_codes'),
        ]);
    }

    public function start(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);
        abort_if($this->twoFactor->isOn($request->user()), 409);
        $this->twoFactor->start($request->user());

        return to_route('two-factor.edit');
    }

    public function confirm(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:10']]);

        $codes = $this->twoFactor->confirm($request->user(), $request->string('code')->toString());

        if ($codes === null) {
            return back()->withErrors(['code' => 'That code is not right. Check the time on your phone is correct, and try the newest code.']);
        }

        return to_route('two-factor.edit')->with('two_factor.new_codes', $codes);
    }

    public function regenerate(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);
        abort_unless($this->twoFactor->isOn($request->user()), 409);

        return to_route('two-factor.edit')->with('two_factor.new_codes', $this->twoFactor->regenerateBackupCodes($request->user()));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);

        $this->twoFactor->turnOff($request->user());

        return to_route('two-factor.edit');
    }
}
