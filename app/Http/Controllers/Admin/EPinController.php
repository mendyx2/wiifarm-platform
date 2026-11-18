<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Package;
use App\Models\Payments\EPin;
use App\Services\Payments\EPins;
use App\Services\Payments\PaymentException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** E-pins: who created each, who used it and when; creating and cancelling (specification section 6). */
class EPinController extends Controller
{
    public function index(Request $request): Response
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(['all', EPin::ACTIVE, EPin::USED, EPin::CANCELLED])],
            'q' => ['nullable', 'string', 'max:40'],
        ]);
        $status = $data['status'] ?? 'all';
        $q = (string) ($data['q'] ?? '');
        /** @var Admin $admin */
        $admin = $request->user('admin');
        $canManage = $admin->hasPermission('epins.manage');

        return Inertia::render('admin/epins', [
            'status' => $status,
            'q' => $q,
            'packages' => Package::where('is_active', true)->orderBy('sort')->get(['id', 'name', 'price']),
            'pins' => EPin::with(['package:id,name', 'buyer:id,username', 'creatorAdmin:id,username', 'usedBy:id,username'])
                ->when($status !== 'all', fn ($query) => $query->where('status', $status))
                ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                    ->whereHas('buyer', fn ($u) => $u->where('username', 'like', "%{$q}%"))
                    ->orWhereHas('usedBy', fn ($u) => $u->where('username', 'like', "%{$q}%"))))
                ->latest('id')->paginate(25)->withQueryString()
                ->through(fn (EPin $p) => [
                    'id' => $p->id,
                    // Only those who may create and cancel pins see an unused pin's code.
                    'code' => $canManage && $p->status === EPin::ACTIVE ? $p->code() : null,
                    'amount' => $p->amount, 'package' => $p->package?->name, 'status' => $p->status,
                    'created_by' => $p->buyer ? '@'.$p->buyer->username : 'Admin @'.$p->creatorAdmin?->username,
                    'reason' => $p->reason, 'used_by' => $p->usedBy?->username, 'used_at' => $p->used_at?->toDateTimeString(),
                    'cancel_reason' => $p->cancel_reason, 'created_at' => $p->created_at->toDateTimeString(),
                ]),
        ]);
    }

    public function store(Request $request, EPins $epins): RedirectResponse
    {
        $data = $request->validate([
            'package_id' => ['required', 'integer', 'exists:packages,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:'.EPins::MAX_PER_FORM],
            'reason' => ['required', 'string', 'max:250'],
            'form_id' => ['required', 'uuid'],
        ], ['reason.required' => 'Say why these pins are being created (for the audit log).']);

        /** @var Admin $admin */
        $admin = $request->user('admin');
        try {
            $pins = $epins->create($admin, Package::findOrFail($data['package_id']), (int) $data['quantity'], $data['reason'], $data['form_id']);
        } catch (PaymentException $e) {
            return back()->withErrors(['quantity' => $e->getMessage()]);
        }

        return to_route('admin.epins.index', ['status' => EPin::ACTIVE])->with('status', $pins->count().' e-pin(s) created.');
    }

    public function cancel(Request $request, EPin $epin, EPins $epins): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:250']], ['reason.required' => 'Give a reason (for the audit log).']);
        /** @var Admin $admin */
        $admin = $request->user('admin');

        try {
            $epins->cancel($admin, $epin, $data['reason']);
        } catch (PaymentException $e) {
            return back()->withErrors(['reason' => $e->getMessage()]);
        }

        return back()->with('status', $epin->created_by_user_id ? 'E-pin cancelled. Its value is back in the buyer\'s VISA wallet.' : 'E-pin cancelled.');
    }
}
