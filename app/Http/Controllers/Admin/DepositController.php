<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Payments\DepositMethod;
use App\Models\Payments\ManualDeposit;
use App\Services\Admin\Audit;
use App\Services\Ledger\LedgerException;
use App\Services\Payments\ManualDeposits;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Admins check bank-transfer deposits and set up the deposit methods (specification section 6). */
class DepositController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->validate(['status' => ['nullable', Rule::in([ManualDeposit::PENDING, ManualDeposit::APPROVED, ManualDeposit::REJECTED, 'all'])]])['status'] ?? ManualDeposit::PENDING;

        return Inertia::render('admin/deposits/index', [
            'status' => $status,
            'deposits' => ManualDeposit::with(['method:id,name', 'user:id,username'])
                ->when($status !== 'all', fn ($q) => $q->where('status', $status))
                ->orderBy('id', $status === ManualDeposit::PENDING ? 'asc' : 'desc')
                ->paginate(25)->withQueryString()->through(fn (ManualDeposit $d) => self::row($d)),
        ]);
    }

    public function show(ManualDeposit $deposit): Response
    {
        return Inertia::render('admin/deposits/show', ['deposit' => self::row($deposit->load(['method', 'user']))]);
    }

    /** The proof, only to admins allowed to see deposits. */
    public function proof(ManualDeposit $deposit): StreamedResponse
    {
        return Storage::disk(ManualDeposits::DISK)->response($deposit->proof_path, headers: ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function approve(Request $request, ManualDeposit $deposit, ManualDeposits $deposits): RedirectResponse
    {
        try {
            $deposits->approve($deposit, $this->admin($request));
        } catch (InvalidArgumentException|LedgerException $e) {
            return back()->withErrors(['deposit' => $e->getMessage()]);
        }

        return to_route('admin.deposits.index')->with('status', "Deposit #{$deposit->id} approved: the money is in the member's main balance.");
    }

    public function reject(Request $request, ManualDeposit $deposit, ManualDeposits $deposits): RedirectResponse
    {
        try {
            $deposits->reject($deposit, $this->admin($request), (string) $request->input('reason'));
        } catch (InvalidArgumentException|LedgerException $e) {
            return back()->withErrors(['reason' => $e->getMessage()]);
        }

        return to_route('admin.deposits.index')->with('status', "Deposit #{$deposit->id} refused. The member has been told why.");
    }

    public function methods(): Response
    {
        return Inertia::render('admin/deposit-methods', [
            'methods' => DepositMethod::orderBy('name')->get()->map(fn (DepositMethod $m) => [
                'id' => $m->id, 'name' => $m->name, 'instructions' => $m->instructions, 'is_active' => $m->is_active,
                'min_amount' => number_format($m->min_amount / 100, 2, '.', ''), 'max_amount' => number_format($m->max_amount / 100, 2, '.', ''),
            ]),
        ]);
    }

    public function saveMethod(Request $request, Audit $audit, ?DepositMethod $method = null): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('deposit_methods', 'name')->ignore($method?->id)],
            'instructions' => ['required', 'string', 'max:2000'],
            'min_amount' => ['required', 'string'], 'max_amount' => ['required', 'string'],
            'is_active' => ['boolean'],
        ], ['instructions.required' => 'Say where to pay: the bank, account number and account name.']);

        $values = ['name' => $data['name'], 'instructions' => trim($data['instructions']), 'is_active' => $request->boolean('is_active')];
        foreach (['min_amount', 'max_amount'] as $field) {
            try {
                $values[$field] = Money::parseNaira($data[$field]);
            } catch (InvalidArgumentException) {
                throw ValidationException::withMessages([$field => 'Enter an amount in naira, e.g. 1000']);
            }
        }
        if ($values['min_amount'] > $values['max_amount']) {
            throw ValidationException::withMessages(['max_amount' => 'The maximum must be at least the minimum.']);
        }

        $method ??= new DepositMethod;
        $old = $method->exists ? $method->only(array_keys($values)) : null;
        $method->fill($values)->save();
        $audit->record($this->admin($request), $old ? 'deposits.method_edit' : 'deposits.method_create', $method, $old, $values);

        return back()->with('status', 'Deposit method saved.');
    }

    /** @return array<string, mixed> */
    private static function row(ManualDeposit $d): array
    {
        return [
            'id' => $d->id, 'member' => $d->user->username, 'member_id' => $d->user_id, 'method' => $d->method->name,
            'amount' => $d->amount, 'reference' => $d->reference, 'status' => $d->status, 'reason' => $d->reject_reason,
            'at' => $d->created_at->toDateTimeString(), 'decided_at' => $d->decided_at?->toDateTimeString(),
            'proof_is_pdf' => str_ends_with(strtolower($d->proof_path), '.pdf'),
        ];
    }

    private function admin(Request $request): Admin
    {
        /** @var Admin */
        return $request->user('admin');
    }
}
