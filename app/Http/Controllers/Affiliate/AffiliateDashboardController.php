<?php

namespace App\Http\Controllers\Affiliate;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\Shop\Order;
use App\Services\Ledger\Ledger;
use App\Services\Ledger\Statement;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** The affiliate's dashboard: their link, the orders it brought, and their wallet. */
class AffiliateDashboardController extends Controller
{
    public function __invoke(Request $request, Ledger $ledger, Statement $statement): Response
    {
        /** @var Affiliate $affiliate */
        $affiliate = $request->user('affiliate');
        $rates = config('pools.schedule.public');

        return Inertia::render('affiliate/dashboard', [
            'affiliate' => ['name' => $affiliate->name(), 'code' => $affiliate->code, 'link' => route('affiliate.link', $affiliate->code)],
            'wallet' => $ledger->balance($ledger->affiliateWallet($affiliate)),
            'statement' => $statement->for($ledger->affiliateWallet($affiliate), 10),
            'orders' => Order::with('items')->where('affiliate_id', $affiliate->id)->whereIn('status', [Order::PAID, Order::FULFILLED])->latest('id')->limit(25)->get()
                ->map(fn (Order $o) => [
                    'id' => $o->id,
                    'number' => $o->number,
                    'status' => $o->status,
                    'units' => $o->units,
                    'commission' => $o->items->sum(fn ($i) => Money::share(max(0, $i->quantity * ($i->unit_price - $rates['member_unit_price'])), $rates['affiliate_share_of_margin'])),
                    'paid_at' => $o->paid_at?->toDateString(),
                ]),
        ]);
    }

    /** Someone followed an affiliate's link: remember the affiliate for 30 days, then show the site. */
    public function link(string $code): RedirectResponse
    {
        $affiliate = Affiliate::where('code', strtoupper($code))->where('status', Affiliate::ACTIVE)->first();

        $response = redirect('/');

        return $affiliate ? $response->withCookie(cookie('wiifarm_ref', $affiliate->code, 60 * 24 * 30)) : $response;
    }
}
