<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\PackageChange;
use App\Services\Ledger\InsufficientFunds;
use App\Services\Ledger\Ledger;
use App\Services\Network\KeyIn;
use App\Services\Network\PlacementException;
use App\Services\Network\SignUpChecks;
use App\Support\Settings;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** "Add Distributor": a member registers someone and pays from their VISA wallet (specification section 2). */
class KeyInController extends Controller
{
    public function create(Request $request, Ledger $ledger): Response
    {
        $member = $request->user();

        return Inertia::render('team/add', [
            'visa' => $ledger->balances($member)['visa'],
            'canKeyIn' => $member->isActivated() && ! $member->isBanned(),
            'packages' => Package::where('is_active', true)->orderBy('sort')->get(['id', 'name', 'price', 'pv']),
            'defaults' => [
                'dial_code' => config('wiifarm.default_dial_code'),
                'country' => config('wiifarm.default_country'),
                'sponsor' => $member->username,
            ],
        ]);
    }

    public function store(Request $request, KeyIn $keyIn, SignUpChecks $checks): RedirectResponse
    {
        // The same form sent twice: the first one already did it.
        $formId = (string) $request->input('form_id');
        if ($formId !== '' && ($done = PackageChange::with('user', 'toPackage')->where('payment_key', 'keyin:'.$request->user()->id.':'.$formId)->first())) {
            return to_route('team')->with('status', "@{$done->user->username} is already a member on {$done->toPackage->name}.");
        }

        SignUpChecks::tidy($request);
        $data = $request->validate(SignUpChecks::rules(keyIn: true) + [
            'package_id' => ['required', 'integer', 'exists:packages,id'],
            'form_id' => ['required', 'uuid'],
            'terms' => ['accepted'],
        ], SignUpChecks::messages() + ['terms.accepted' => 'Confirm that the person accepts the member terms.']);

        $sponsor = $checks->sponsorFor($data['sponsor']);
        $parent = $checks->parentFor($data['parent'] ?? null, $data['leg'] ?? null, $sponsor);
        $package = Package::findOrFail($data['package_id']);

        try {
            $change = $keyIn->register($request->user(), $data, $sponsor, $parent, $package, $data['form_id']);
        } catch (InsufficientFunds) {
            throw ValidationException::withMessages(['package_id' => 'Your VISA wallet does not have the ₦'.number_format($package->price / 100).' this package costs.']);
        } catch (PlacementException $e) {
            throw ValidationException::withMessages(['sponsor' => $e->getMessage()]);
        }

        $new = $change->user;
        if ($change->wasRecentlyCreated) {
            event(new Registered($new));
            if (Settings::get('verification.email_required')) {
                $new->sendEmailVerificationNotification();
            }
        }

        return to_route('team')->with('status', "@{$new->username} is now a member on {$package->name}. They sign in with the username and password you chose, and collect their packs with the pickup code on their orders page.");
    }
}
