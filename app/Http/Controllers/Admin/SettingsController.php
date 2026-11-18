<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Services\Admin\Audit;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

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
        'matching.autoship_hold' => 'Until a member buys 10 PV of products in the month, 20% of each matching bonus goes to their autoship wallet.',
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
        ]);
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
