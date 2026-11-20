<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Services\Admin\Audit;
use App\Support\Money;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/**
 * System settings (specification section 11). For now, the verification
 * switches; more settings join as their features are built. Seeing them needs
 * settings.view; changing them is for super-admins (settings.edit).
 */
class SettingsController extends Controller
{
    /** The switches on this page, with what each one means. */
    private const SWITCHES = [
        'verification.email_required' => 'Members must confirm their email address before they can use their dashboard.',
        'verification.mobile_required' => 'Members must confirm their phone number with an SMS code before they can use their dashboard.',
        'withdrawals.kyc_required' => 'Members must have their identity verified (KYC) before they can withdraw.',
        'matching.autoship_hold' => 'Until a member makes their monthly purchase, 20% of each matching bonus goes to their autoship wallet.',
        'messages.email' => 'Members receive messages by email (welcome, payments, withdrawals, identity checks, awards, orders, autoship reminders). Each message can also be switched off on the Messages page.',
    ];

    public function edit(): Response
    {
        return Inertia::render('admin/settings', [
            'switches' => collect(self::SWITCHES)->map(fn (string $help, string $key) => [
                'key' => $key,
                'help' => $help,
                'value' => (bool) Settings::get($key),
            ])->values(),
            'leadership' => ['team' => Settings::get('leadership.team'), 'levels' => (int) Settings::get('leadership.levels')],
            'monthlyPv' => (float) Settings::get('activity.monthly_pv'),
            'transfers' => [
                'enabled' => (bool) Settings::get('transfers.enabled'),
                'min' => number_format(Settings::get('transfers.min') / 100, 2, '.', ''), 'max' => number_format(Settings::get('transfers.max') / 100, 2, '.', ''),
                'fixed_charge' => number_format(Settings::get('transfers.fixed_charge') / 100, 2, '.', ''), 'percent_charge' => (string) Settings::get('transfers.percent_charge'),
            ],
        ]);
    }

    /** Balance transfers between members: on or off, limits and charges (section 6). */
    public function updateTransfers(Request $request, Audit $audit): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['boolean'], 'min' => ['required', 'string'], 'max' => ['required', 'string'], 'fixed_charge' => ['required', 'string'],
            'percent_charge' => ['required', 'numeric', 'min:0', 'max:50'],
        ]);
        $new = ['transfers.enabled' => $request->boolean('enabled'), 'transfers.percent_charge' => number_format((float) $data['percent_charge'], 2, '.', '')];
        foreach (['min', 'max', 'fixed_charge'] as $field) {
            try {
                $new['transfers.'.$field] = Money::parseNaira($data[$field]);
            } catch (InvalidArgumentException) {
                throw ValidationException::withMessages([$field => 'Enter an amount in naira, e.g. 1000']);
            }
        }
        if ($new['transfers.min'] <= 0 || $new['transfers.min'] > $new['transfers.max']) {
            throw ValidationException::withMessages(['max' => 'The maximum must be at least the minimum, and the minimum more than zero.']);
        }
        /** @var Admin $admin */
        $admin = $request->user('admin');
        $old = array_map(fn ($k) => Settings::get($k), array_combine(array_keys($new), array_keys($new)));
        DB::transaction(function () use ($new, $old, $admin, $audit) {
            foreach ($new as $key => $value) {
                Settings::set($key, $value, $admin->id);
            }
            $audit->record($admin, 'settings.edit', null, $old, $new, 'Balance transfers');
        });

        return back()->with('status', 'Balance transfer settings saved.');
    }

    /** The monthly purchase that keeps a member active (Revised Baseline Rules: 10.5 PV). */
    public function updateActivity(Request $request, Audit $audit): RedirectResponse
    {
        $data = $request->validate(['monthly_pv' => ['required', 'numeric', 'min:0', 'max:1000', 'decimal:0,2']]);
        /** @var Admin $admin */
        $admin = $request->user('admin');
        $old = (float) Settings::get('activity.monthly_pv');
        $new = round((float) $data['monthly_pv'], 2);

        if ($old !== $new) {
            DB::transaction(function () use ($admin, $audit, $old, $new) {
                Settings::set('activity.monthly_pv', $new, $admin->id);
                $audit->record($admin, 'settings.edit', null, ['activity.monthly_pv' => $old], ['activity.monthly_pv' => $new], 'Monthly purchase requirement');
            });
        }

        return back()->with('status', 'Monthly purchase requirement saved.');
    }

    /** Who counts as a leader's team for the leadership bonus (open decision 3). */
    public function updateLeadership(Request $request, Audit $audit): RedirectResponse
    {
        $data = $request->validate(['team' => ['required', 'in:tree,sponsor'], 'levels' => ['required', 'integer', 'min:0', 'max:100']]);
        /** @var Admin $admin */
        $admin = $request->user('admin');

        DB::transaction(function () use ($data, $admin, $audit) {
            $old = ['leadership.team' => Settings::get('leadership.team'), 'leadership.levels' => (int) Settings::get('leadership.levels')];
            $new = ['leadership.team' => $data['team'], 'leadership.levels' => (int) $data['levels']];
            if ($old !== $new) {
                foreach ($new as $key => $value) {
                    Settings::set($key, $value, $admin->id);
                }
                $audit->record($admin, 'settings.edit', null, $old, $new, 'Leadership bonus team');
            }
        });

        return back()->with('status', 'Leadership team saved. It applies from the next monthly run.');
    }

    public function update(Request $request, Audit $audit): RedirectResponse
    {
        $rules = collect(self::SWITCHES)->mapWithKeys(fn ($help, $key) => [str_replace('.', '_', $key) => ['required', 'boolean']])->all();
        $data = $request->validate($rules);

        /** @var Admin $admin */
        $admin = $request->user('admin');

        DB::transaction(function () use ($data, $admin, $audit) {
            foreach (array_keys(self::SWITCHES) as $key) {
                $old = (bool) Settings::get($key);
                $new = (bool) $data[str_replace('.', '_', $key)];

                if ($old !== $new) {
                    Settings::set($key, $new, $admin->id);
                    $audit->record($admin, 'settings.edit', null, [$key => $old], [$key => $new]);
                }
            }
        });

        return back()->with('status', 'Settings saved.');
    }
}
