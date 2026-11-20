<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Payments\UsdtDeposit;
use App\Services\Admin\Audit;
use App\Services\Usdt\TronAddress;
use App\Services\Usdt\TronGrid;
use App\Services\Usdt\UsdtDeposits;
use App\Support\Money;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/** USDT deposits for admins: settings, deposits to check, and payments that fit no deposit (section 6). */
class UsdtController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->validate(['status' => ['nullable', Rule::in([UsdtDeposit::REVIEW, UsdtDeposit::PAID, UsdtDeposit::WAITING, UsdtDeposit::EXPIRED, UsdtDeposit::REJECTED, 'all'])]])['status'] ?? UsdtDeposit::REVIEW;

        return Inertia::render('admin/usdt', [
            'status' => $status,
            'settings' => [
                'enabled' => (bool) Settings::get('usdt.enabled'), 'address' => (string) Settings::get('usdt.address'),
                'naira_per_usdt' => (string) Settings::get('usdt.naira_per_usdt'), 'ready' => UsdtDeposits::ready(),
                'network' => config('usdt.network'), 'api_key' => (bool) config('usdt.api_key'),
            ],
            'explorer' => TronGrid::network()['explorer'],
            'deposits' => UsdtDeposit::with('user:id,username')
                ->when($status !== 'all', fn ($q) => $q->where('status', $status))->latest('id')
                ->paginate(25)->withQueryString()->through(fn (UsdtDeposit $d) => [
                    'id' => $d->id, 'member' => $d->user->username, 'amount' => $d->amount, 'usdt' => UsdtDeposit::usdt($d->units),
                    'received' => $d->received_units !== null ? UsdtDeposit::usdt($d->received_units) : null,
                    'suggested' => $d->received_units !== null ? (string) number_format(intdiv($d->received_units * $d->rate_kobo, 1_000_000) / 100, 2, '.', '') : null,
                    'status' => $d->status, 'txid' => $d->txid, 'sender' => $d->sender, 'sent_at' => $d->sent_at?->toDateTimeString(),
                    'at' => $d->created_at->toDateTimeString(), 'expires_at' => $d->expires_at->toDateTimeString(), 'note' => $d->note, 'credited' => $d->credited,
                ]),
            'unmatched' => DB::table('usdt_transfers')->where('status', 'unmatched')->latest('id')->limit(50)->get()
                ->map(fn ($t) => ['txid' => $t->txid, 'usdt' => UsdtDeposit::usdt((int) $t->units), 'sender' => $t->sender, 'sent_at' => $t->sent_at]),
        ]);
    }

    public function settings(Request $request, Audit $audit): RedirectResponse
    {
        $data = $request->validate([
            'address' => ['nullable', 'string', 'max:34'],
            'naira_per_usdt' => ['required', 'numeric', 'min:0', 'max:100000'],
            'enabled' => ['boolean'],
        ]);
        $address = trim((string) ($data['address'] ?? ''));
        if ($address !== '' && ! TronAddress::isValid($address)) {
            throw ValidationException::withMessages(['address' => 'This is not a valid Tron address (it starts with T and has 34 characters). Copy it again from the wallet.']);
        }
        if ($request->boolean('enabled') && ($address === '' || (float) $data['naira_per_usdt'] <= 0)) {
            throw ValidationException::withMessages(['enabled' => 'Set the address and the rate before switching USDT deposits on.']);
        }
        /** @var Admin $admin */
        $admin = $request->user('admin');
        $new = ['usdt.enabled' => $request->boolean('enabled'), 'usdt.address' => $address, 'usdt.naira_per_usdt' => round((float) $data['naira_per_usdt'], 2)];
        $old = array_map(fn ($k) => Settings::get($k), array_combine(array_keys($new), array_keys($new)));
        DB::transaction(function () use ($new, $old, $admin, $audit) {
            foreach ($new as $key => $value) {
                Settings::set($key, $value, $admin->id);
            }
            $audit->record($admin, 'settings.edit', null, $old, $new, 'USDT deposits');
        });

        return back()->with('status', 'USDT settings saved.');
    }

    public function approve(Request $request, UsdtDeposit $deposit, UsdtDeposits $usdt): RedirectResponse
    {
        try {
            $usdt->approve($deposit, $this->admin($request), Money::parseNaira((string) $request->input('amount')));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        return back()->with('status', "USDT deposit #{$deposit->id} approved.");
    }

    public function reject(Request $request, UsdtDeposit $deposit, UsdtDeposits $usdt): RedirectResponse
    {
        try {
            $usdt->reject($deposit, $this->admin($request), (string) $request->input('reason'));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['reason' => $e->getMessage()]);
        }

        return back()->with('status', "USDT deposit #{$deposit->id} refused; the payment is free to be claimed by its owner.");
    }

    private function admin(Request $request): Admin
    {
        /** @var Admin */
        return $request->user('admin');
    }
}
