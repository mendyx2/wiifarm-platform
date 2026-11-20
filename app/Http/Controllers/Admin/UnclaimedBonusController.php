<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pools\UnclaimedBonus;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** The Unclaimed Bonus Reserve: every share nobody could receive, and where it came from. */
class UnclaimedBonusController extends Controller
{
    public function index(Request $request): Response
    {
        $data = $request->validate([
            'pool' => ['nullable', Rule::in(array_keys(config('pools.pools')))],
            'bonus' => ['nullable', Rule::in(array_keys(UnclaimedBonus::BONUSES))],
            'reason' => ['nullable', Rule::in(array_keys(UnclaimedBonus::REASONS))],
        ]);
        $filter = fn ($q) => $q
            ->when($data['pool'] ?? null, fn ($w, $v) => $w->where('pool', $v))
            ->when($data['bonus'] ?? null, fn ($w, $v) => $w->where('bonus', $v))
            ->when($data['reason'] ?? null, fn ($w, $v) => $w->where('reason', $v));

        $by = fn (string $column) => $filter(UnclaimedBonus::query())->groupBy($column)
            ->selectRaw("{$column} as k, COUNT(*) as n, SUM(amount) as amount")->orderByDesc('amount')->get()
            ->map(fn ($r) => ['key' => $r->k, 'count' => (int) $r->n, 'amount' => (int) $r->amount]);

        return Inertia::render('admin/unclaimed', [
            'filters' => ['pool' => $data['pool'] ?? null, 'bonus' => $data['bonus'] ?? null, 'reason' => $data['reason'] ?? null],
            'labels' => [
                'pools' => collect(config('pools.pools'))->map(fn ($p) => $p['name']),
                'bonuses' => UnclaimedBonus::BONUSES,
                'reasons' => UnclaimedBonus::REASONS,
            ],
            'total' => (int) $filter(UnclaimedBonus::query())->sum('amount'),
            'byPool' => $by('pool'),
            'byBonus' => $by('bonus'),
            'byReason' => $by('reason'),
            'inflows' => $filter(UnclaimedBonus::with(['beneficiary:id,username', 'source:id,username']))->latest('id')->paginate(50)->withQueryString()
                ->through(fn (UnclaimedBonus $u) => [
                    'id' => $u->id, 'at' => $u->created_at->toDateTimeString(), 'pool' => $u->pool, 'bonus' => $u->bonus,
                    'generation' => $u->generation, 'reason' => $u->reason, 'amount' => $u->amount, 'memo' => $u->memo,
                    'beneficiary' => $u->beneficiary?->username, 'source' => $u->source?->username, 'transaction' => $u->transaction_id,
                ]),
        ]);
    }
}
