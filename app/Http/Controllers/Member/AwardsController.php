<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Admin\AwardController;
use App\Http\Controllers\Controller;
use App\Models\Compensation\Award;
use App\Services\Compensation\Awards;
use App\Services\Ledger\Ledger;
use App\Services\Payments\PaymentException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** A member's awards: the rank ladder, their progress, and the cash-or-gift choice (specification 5.8 and 5.11). */
class AwardsController extends Controller
{
    public function index(Request $request, Ledger $ledger, Awards $awards): Response
    {
        $member = $request->user()->load('package');
        $pv = $ledger->pvBalances($member);
        $mine = Award::where('user_id', $member->id)->orderBy('id')->get();

        return Inertia::render('awards', [
            'lesserLeg' => intdiv(min($pv['L']['lifetime'], $pv['R']['lifetime']), 100),
            'package' => $member->package?->name,
            'ranks' => collect(config('awards.ranks'))->map(fn ($r, $code) => [
                'code' => $code, 'name' => $r['name'], 'pv' => $r['pv'], 'cash' => $r['cash'], 'gift' => $r['gift'], 'package' => $r['package'],
                'needs' => $r['needs'] ? config('awards.ranks')[$r['needs']]['name'] : null,
                'earned' => $mine->first(fn (Award $a) => $a->kind === Award::RANK && $a->code === $code && $a->status !== Award::REJECTED) !== null,
            ])->values(),
            'teamRepurchase' => intdiv($awards->teamRepurchasePv()[$member->id] ?? 0, 100),
            'tiers' => collect(config('awards.repurchase_tiers'))->map(fn ($cash, $pv) => ['pv' => $pv, 'cash' => $cash])->values(),
            'awards' => $mine->sortByDesc('id')->values()->map(fn (Award $a) => AwardController::row($a)),
        ]);
    }

    public function choose(Request $request, Award $award, Awards $awards): RedirectResponse
    {
        $data = $request->validate(['choice' => ['required', 'in:cash,gift']]);
        try {
            $awards->choose($request->user(), $award, $data['choice']);
        } catch (PaymentException $e) {
            throw ValidationException::withMessages(['choice' => $e->getMessage()]);
        }

        return back()->with('status', $data['choice'] === 'gift' ? "You chose the {$award->gift}." : 'You chose the cash.');
    }
}
