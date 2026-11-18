<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Affiliate;
use App\Models\Payments\Withdrawal;
use App\Models\Payments\WithdrawalMethod;
use App\Models\Stockists\Store;
use App\Models\User;
use App\Services\Admin\Audit;
use App\Services\Ledger\LedgerException;
use App\Services\Payments\Withdrawals;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/** Admins approve or reject withdrawals, and set up the methods (specification section 6). */
class WithdrawalController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->validate(['status' => ['nullable', Rule::in([Withdrawal::PENDING, Withdrawal::PAID, Withdrawal::REJECTED, 'all'])]])['status'] ?? Withdrawal::PENDING;

        return Inertia::render('admin/withdrawals/index', [
            'status' => $status,
            'withdrawals' => Withdrawal::with('method:id,name')
                ->when($status !== 'all', fn ($q) => $q->where('status', $status))
                ->orderBy('id', $status === Withdrawal::PENDING ? 'asc' : 'desc')
                ->paginate(25)->withQueryString()
                ->through(fn (Withdrawal $w) => self::row($w) + ['who' => self::who($w)]),
        ]);
    }

    public function show(Withdrawal $withdrawal): Response
    {
        $withdrawal->load('method');

        return Inertia::render('admin/withdrawals/show', [
            'withdrawal' => self::row($withdrawal) + [
                'who' => self::who($withdrawal),
                'member_id' => $withdrawal->holder_type === Withdrawal::MEMBER ? $withdrawal->holder_id : null,
                'details' => collect($withdrawal->method->fields)->map(fn ($f) => ['label' => $f['label'], 'value' => $withdrawal->details[$f['key']] ?? '—'])->values(),
            ],
        ]);
    }

    public function approve(Request $request, Withdrawal $withdrawal, Withdrawals $withdrawals): RedirectResponse
    {
        try {
            $withdrawals->approve($withdrawal, $this->admin($request), (string) $request->input('reference'));
        } catch (InvalidArgumentException|LedgerException $e) {
            return back()->withErrors(['reference' => $e->getMessage()]);
        }

        return to_route('admin.withdrawals.index')->with('status', "Withdrawal #{$withdrawal->id} marked as paid.");
    }

    public function reject(Request $request, Withdrawal $withdrawal, Withdrawals $withdrawals): RedirectResponse
    {
        try {
            $withdrawals->reject($withdrawal, $this->admin($request), (string) $request->input('reason'));
        } catch (InvalidArgumentException|LedgerException $e) {
            return back()->withErrors(['reason' => $e->getMessage()]);
        }

        return to_route('admin.withdrawals.index')->with('status', "Withdrawal #{$withdrawal->id} refused; the money went back to the wallet.");
    }

    public function methods(): Response
    {
        return Inertia::render('admin/withdrawal-methods', [
            'methods' => WithdrawalMethod::orderBy('name')->get()->map(fn (WithdrawalMethod $m) => [
                'id' => $m->id, 'name' => $m->name, 'is_active' => $m->is_active,
                'min_amount' => number_format($m->min_amount / 100, 2, '.', ''), 'max_amount' => number_format($m->max_amount / 100, 2, '.', ''),
                'fixed_charge' => number_format($m->fixed_charge / 100, 2, '.', ''), 'percent_charge' => (string) $m->percent_charge,
                'fields' => implode(', ', array_column($m->fields, 'label')),
            ]),
        ]);
    }

    public function saveMethod(Request $request, Audit $audit, ?WithdrawalMethod $method = null): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('withdrawal_methods', 'name')->ignore($method?->id)],
            'min_amount' => ['required', 'string'], 'max_amount' => ['required', 'string'], 'fixed_charge' => ['required', 'string'],
            'percent_charge' => ['required', 'numeric', 'min:0', 'max:50'],
            'fields' => ['required', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ], ['fields.required' => 'List the details to ask for, separated by commas.']);

        $values = [];
        foreach (['min_amount', 'max_amount', 'fixed_charge'] as $field) {
            try {
                $values[$field] = Money::parseNaira($data[$field]);
            } catch (InvalidArgumentException) {
                throw ValidationException::withMessages([$field => 'Enter an amount in naira, e.g. 1000']);
            }
        }
        if ($values['min_amount'] > $values['max_amount']) {
            throw ValidationException::withMessages(['max_amount' => 'The maximum must be at least the minimum.']);
        }
        $labels = array_values(array_filter(array_map('trim', explode(',', $data['fields']))));
        $values += [
            'name' => $data['name'],
            'percent_charge' => number_format((float) $data['percent_charge'], 2, '.', ''),
            'fields' => array_map(fn ($l) => ['key' => Str::slug($l, '_'), 'label' => $l], $labels),
            'is_active' => $request->boolean('is_active'),
        ];

        $method ??= new WithdrawalMethod;
        $old = $method->exists ? $method->only(array_keys($values)) : null;
        $method->fill($values)->save();
        $audit->record($this->admin($request), $old ? 'withdrawals.method_edit' : 'withdrawals.method_create', $method, $old, $values);

        return back()->with('status', 'Withdrawal method saved.');
    }

    /** @return list<array<string, mixed>> */
    public static function methodOptions(): array
    {
        return WithdrawalMethod::where('is_active', true)->orderBy('name')->get()->map(fn (WithdrawalMethod $m) => [
            'id' => $m->id, 'name' => $m->name, 'min' => $m->min_amount, 'max' => $m->max_amount,
            'fixed_charge' => $m->fixed_charge, 'percent_charge' => (string) $m->percent_charge, 'fields' => $m->fields,
        ])->all();
    }

    /** @return array<string, mixed> */
    public static function row(Withdrawal $w): array
    {
        return [
            'id' => $w->id, 'method' => $w->method->name, 'amount' => $w->amount, 'charge' => $w->charge, 'payable' => $w->payable,
            'status' => $w->status, 'reason' => $w->reject_reason, 'reference' => $w->reference, 'holder_type' => $w->holder_type,
            'requested_at' => $w->created_at->toDateTimeString(), 'decided_at' => $w->decided_at?->toDateTimeString(),
        ];
    }

    private static function who(Withdrawal $w): string
    {
        return match ($w->holder_type) {
            Withdrawal::MEMBER => '@'.(User::find($w->holder_id)?->username ?? '?').' · main balance',
            Withdrawal::STORE => (Store::find($w->holder_id)?->name ?? 'Store').' · stockist wallet',
            Withdrawal::AFFILIATE => (($a = Affiliate::find($w->holder_id)) ? $a->code.' '.$a->name() : 'Affiliate').' · affiliate wallet',
            default => $w->holder_type,
        };
    }

    private function admin(Request $request): Admin
    {
        /** @var Admin */
        return $request->user('admin');
    }
}
