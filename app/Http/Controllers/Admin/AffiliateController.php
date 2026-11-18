<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Affiliate;
use App\Models\Shop\Order;
use App\Services\Admin\Audit;
use App\Services\Ledger\Ledger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Affiliates: who they are, what they earned, and banning (specification section 8). */
class AffiliateController extends Controller
{
    public function index(Request $request, Ledger $ledger): Response
    {
        $q = (string) ($request->validate(['q' => ['nullable', 'string', 'max:80']])['q'] ?? '');

        return Inertia::render('admin/affiliates', [
            'q' => $q,
            'affiliates' => Affiliate::query()
                ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('code', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")->orWhere('first_name', 'like', "%{$q}%")->orWhere('last_name', 'like', "%{$q}%")))
                ->latest('id')->paginate(25)->withQueryString()
                ->through(fn (Affiliate $a) => [
                    'id' => $a->id, 'code' => $a->code, 'name' => $a->name(), 'email' => $a->email, 'phone' => $a->phone,
                    'status' => $a->status, 'ban_reason' => $a->ban_reason,
                    'wallet' => $ledger->balance($ledger->affiliateWallet($a)),
                    'orders' => Order::where('affiliate_id', $a->id)->whereIn('status', [Order::PAID, Order::FULFILLED])->count(),
                    'joined' => $a->created_at->toDateString(),
                ]),
        ]);
    }

    public function standing(Request $request, Affiliate $affiliate, Audit $audit): RedirectResponse
    {
        $data = $request->validate(['ban' => ['required', 'boolean'], 'reason' => ['required', 'string', 'max:250']], ['reason.required' => 'Give a reason (for the audit log).']);
        $new = $data['ban'] ? Affiliate::BANNED : Affiliate::ACTIVE;

        if ($affiliate->status !== $new) {
            $old = ['status' => $affiliate->status, 'ban_reason' => $affiliate->ban_reason];
            $affiliate->forceFill(['status' => $new, 'ban_reason' => $data['ban'] ? $data['reason'] : null])->save();
            /** @var Admin $admin */
            $admin = $request->user('admin');
            $audit->record($admin, $data['ban'] ? 'affiliates.ban' : 'affiliates.unban', $affiliate, $old, ['status' => $new], $data['reason']);
        }

        return back()->with('status', $data['ban'] ? "{$affiliate->code} banned. New sales through their link earn nothing." : "{$affiliate->code} active again.");
    }
}
