<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Compensation\Award;
use App\Services\Compensation\Awards;
use App\Services\Payments\PaymentException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Awards waiting for approval, and those decided (specification 5.8 and 5.11). */
class AwardController extends Controller
{
    private const FILTERS = ['pending', 'held', 'gift', 'all'];

    public function index(Request $request): Response
    {
        $status = $request->validate(['status' => ['nullable', Rule::in(self::FILTERS)]])['status'] ?? 'pending';

        return Inertia::render('admin/awards', [
            'status' => $status,
            'ranks' => collect(config('awards.ranks'))->map(fn ($r) => ['name' => $r['name'], 'pv' => $r['pv'], 'cash' => $r['cash'], 'gift' => $r['gift'], 'package' => $r['package'], 'needs' => $r['needs'] ? config('awards.ranks')[$r['needs']]['name'] : null])->values(),
            'tiers' => collect(config('awards.repurchase_tiers'))->map(fn ($cash, $pv) => ['pv' => $pv, 'cash' => $cash])->values(),
            'awards' => Award::with(['user:id,username,first_name,last_name', 'decidedBy:id,username'])
                ->when($status === 'gift', fn ($q) => $q->where('status', Award::GIFT))
                ->when(in_array($status, ['pending', 'held'], true), fn ($q) => $q->where('status', $status))
                ->orderBy($status === 'pending' ? 'id' : 'updated_at', $status === 'pending' ? 'asc' : 'desc')
                ->paginate(25)->withQueryString()
                ->through(fn (Award $a) => self::row($a) + [
                    'member' => ['id' => $a->user->id, 'username' => $a->user->username, 'name' => trim($a->user->first_name.' '.$a->user->last_name)],
                    'decided_by' => $a->decidedBy?->username,
                ]),
        ]);
    }

    public function approve(Request $request, Award $award, Awards $awards): RedirectResponse
    {
        try {
            $award = $awards->approve($this->admin($request), $award);
        } catch (PaymentException $e) {
            return back()->withErrors(['award' => $e->getMessage()]);
        }

        return back()->with('status', match ($award->status) {
            Award::HELD => "{$award->name} approved. It is held until its pool has the money, then paid automatically.",
            Award::GIFT => "{$award->name} approved as a gift ({$award->gift}); its value is set aside. Mark it delivered once handed over.",
            default => "{$award->name} approved and paid.",
        });
    }

    public function reject(Request $request, Award $award, Awards $awards): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:250']], ['reason.required' => 'Give a reason; the member sees it.']);
        try {
            $awards->reject($this->admin($request), $award, $data['reason']);
        } catch (PaymentException $e) {
            return back()->withErrors(['reason' => $e->getMessage()]);
        }

        return back()->with('status', "{$award->name} refused.");
    }

    public function delivered(Request $request, Award $award, Awards $awards): RedirectResponse
    {
        try {
            $awards->delivered($this->admin($request), $award);
        } catch (PaymentException $e) {
            return back()->withErrors(['award' => $e->getMessage()]);
        }

        return back()->with('status', "{$award->gift} marked as delivered.");
    }

    /** @return array<string, mixed> */
    public static function row(Award $a): array
    {
        return [
            'id' => $a->id, 'kind' => $a->kind, 'name' => $a->name, 'amount' => $a->amount, 'gift' => $a->gift, 'choice' => $a->choice,
            'measure' => $a->measure, 'status' => $a->currentStatus(), 'reason' => $a->reason, 'at' => $a->created_at->toDateString(),
            'decided_at' => $a->decided_at?->toDateString(),
        ];
    }

    private function admin(Request $request): Admin
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');

        return $admin;
    }
}
