<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\KycSubmission;
use App\Models\SponsorChange;
use App\Models\User;
use App\Services\Admin\Audit;
use App\Services\Admin\MemberStanding;
use App\Services\Ledger\Ledger;
use App\Services\Members\ChangeSponsor;
use App\Services\Network\Genealogy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/**
 * The admin's member screens (specification section 11, "Members"): search
 * and filter, a member's details, and the actions on a member. Wallets, the
 * tree, signing in as the member and notifications join in later phases.
 */
class MemberController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'banned'])],
            'kyc' => ['nullable', Rule::in(['unverified', 'pending', 'verified'])],
            'paid' => ['nullable', Rule::in(['paid', 'free'])],
        ]);

        $members = User::query()
            ->when($filters['q'] ?? null, function (Builder $query, string $q) {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q).'%';
                $digits = preg_replace('/\D/', '', $q);
                $query->where(fn (Builder $w) => $w
                    ->where('username', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    // "Amaka Okafor": first and last name typed together
                    ->when(str_contains(trim($q), ' '), function (Builder $n) use ($q) {
                        [$first, $last] = explode(' ', trim($q), 2);
                        $n->orWhere(fn (Builder $both) => $both->where('first_name', 'like', $first.'%')->where('last_name', 'like', trim($last).'%'));
                    })
                    ->when(strlen($digits) >= 4, fn (Builder $m) => $m->orWhere('mobile', 'like', '%'.ltrim($digits, '0').'%')));
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['kyc'] ?? null, fn (Builder $query, string $kyc) => $query->where('kyc_status', $kyc))
            ->when(($filters['paid'] ?? null) === 'paid', fn (Builder $query) => $query->whereNotNull('activated_at'))
            ->when(($filters['paid'] ?? null) === 'free', fn (Builder $query) => $query->whereNull('activated_at'))
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (User $u) => [
                'id' => $u->id,
                'username' => $u->username,
                'name' => $u->name,
                'email' => $u->email,
                'mobile' => '+'.$u->dial_code.' '.$u->mobile,
                'status' => $u->status,
                'kyc_status' => $u->kyc_status,
                'paid' => $u->isActivated(),
                'withdrawals_blocked' => $u->withdrawals_blocked,
                'joined' => $u->created_at->toDateString(),
            ]);

        return Inertia::render('admin/members/index', [
            'members' => $members,
            'filters' => (object) array_filter($filters),
        ]);
    }

    public function show(User $member, Ledger $ledger, Genealogy $genealogy): Response
    {
        $member->load('sponsor');
        $kyc = KycSubmission::where('user_id', $member->id)->latest('id')->first();

        return Inertia::render('admin/members/show', [
            'member' => [
                'id' => $member->id,
                'username' => $member->username,
                'first_name' => $member->first_name,
                'last_name' => $member->last_name,
                'name' => $member->name,
                'email' => $member->email,
                'email_verified' => $member->email_verified_at !== null,
                'dial_code' => $member->dial_code,
                'mobile' => $member->mobile,
                'mobile_verified' => $member->mobile_verified_at !== null,
                'country' => $member->country,
                'state' => $member->state,
                'city' => $member->city,
                'address' => $member->address,
                'status' => $member->status,
                'ban_reason' => $member->ban_reason,
                'withdrawals_blocked' => $member->withdrawals_blocked,
                'withdrawals_block_reason' => $member->withdrawals_block_reason,
                'kyc_status' => $member->kyc_status,
                'paid' => $member->isActivated(),
                'activated_at' => $member->activated_at?->toDateTimeString(),
                'joined' => $member->created_at->toDateTimeString(),
                'two_factor' => $member->two_factor_confirmed_at !== null,
                'is_company' => $member->is(User::company()),
                'sponsor' => $member->sponsor ? ['id' => $member->sponsor->id, 'username' => $member->sponsor->username, 'name' => $member->sponsor->name] : null,
                'referrals' => $member->referrals()->count(),
            ],
            'network' => $this->network($member, $genealogy),
            'wallets' => $ledger->balances($member),
            'walletLabels' => config('ledger.member_wallets'),
            'kyc' => $kyc ? ['id' => $kyc->id, 'status' => $kyc->status, 'submitted_at' => $kyc->created_at->toDateTimeString()] : null,
            'sponsorChanges' => SponsorChange::with(['oldSponsor:id,username', 'newSponsor:id,username'])
                ->where('user_id', $member->id)->latest('id')->limit(20)->get()
                ->map(fn (SponsorChange $c) => [
                    'id' => $c->id,
                    'from' => $c->oldSponsor?->username,
                    'to' => $c->newSponsor?->username,
                    'reason' => $c->reason,
                    'at' => $c->created_at->toDateTimeString(),
                ]),
            'history' => AuditLog::with('admin:id,username')
                ->where('subject_type', 'User')->where('subject_id', $member->id)
                ->latest('id')->limit(20)->get()
                ->map(fn (AuditLog $e) => [
                    'id' => $e->id,
                    'action' => $e->action,
                    'admin' => $e->admin?->username,
                    'note' => $e->note,
                    'at' => $e->created_at->toDateTimeString(),
                ]),
        ]);
    }

    /** Edit a member's contact details. The username is permanent. */
    public function update(Request $request, User $member, Audit $audit): RedirectResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($member->id)],
            'dial_code' => ['required', 'string', 'regex:/^[0-9]{1,4}$/'],
            'mobile' => ['required', 'string', 'regex:/^[0-9]{6,15}$/'],
            'country' => ['required', 'string', 'size:2', 'alpha'],
            'state' => ['nullable', 'string', 'max:80'],
            'city' => ['nullable', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:255'],
            'note' => ['required', 'string', 'max:250'],
        ], ['note.required' => 'Say why the details are changed (for the audit log).']);

        $note = $data['note'];
        unset($data['note']);
        $data['country'] = strtoupper($data['country']);

        DB::transaction(function () use ($member, $data, $note, $request, $audit) {
            $member->fill($data);
            $changed = $member->getDirty();

            if ($changed === []) {
                return;
            }

            // A new email address or phone number has to be confirmed again.
            if (array_key_exists('email', $changed)) {
                $member->email_verified_at = null;
            }
            if (array_key_exists('mobile', $changed) || array_key_exists('dial_code', $changed)) {
                $member->mobile_verified_at = null;
            }

            $old = array_intersect_key($member->getOriginal(), $changed);
            $member->save();

            $audit->record($this->admin($request), 'members.edit', $member, $old, $changed, $note);
        });

        return back()->with('status', 'Details saved.');
    }

    public function ban(Request $request, User $member, MemberStanding $standing): RedirectResponse
    {
        return $this->attempt(fn () => $standing->ban($this->admin($request), $member, (string) $request->input('reason')), 'ban_reason', 'Member banned and signed out.');
    }

    public function unban(Request $request, User $member, MemberStanding $standing): RedirectResponse
    {
        return $this->attempt(fn () => $standing->unban($this->admin($request), $member, (string) $request->input('reason')), 'unban_reason', 'Ban lifted.');
    }

    public function blockWithdrawals(Request $request, User $member, MemberStanding $standing): RedirectResponse
    {
        return $this->attempt(fn () => $standing->blockWithdrawals($this->admin($request), $member, (string) $request->input('reason')), 'block_reason', 'Withdrawals blocked.');
    }

    public function unblockWithdrawals(Request $request, User $member, MemberStanding $standing): RedirectResponse
    {
        return $this->attempt(fn () => $standing->unblockWithdrawals($this->admin($request), $member, (string) $request->input('reason')), 'unblock_reason', 'Withdrawals open again.');
    }

    public function changeSponsor(Request $request, User $member, ChangeSponsor $changeSponsor, Audit $audit): RedirectResponse
    {
        $request->validate([
            'sponsor' => ['required', 'string'],
            'reason' => ['required', 'string', 'max:250'],
        ], ['sponsor.required' => 'Type the new sponsor\'s username.', 'reason.required' => 'Give a reason for the change.']);

        $newSponsor = User::where('username', trim($request->string('sponsor')))->first();
        if ($newSponsor === null) {
            return back()->withErrors(['sponsor' => 'No member has that username.']);
        }

        $admin = $this->admin($request);

        return $this->attempt(function () use ($member, $newSponsor, $request, $admin, $changeSponsor, $audit) {
            DB::transaction(function () use ($member, $newSponsor, $request, $admin, $changeSponsor, $audit) {
                $oldSponsorId = $member->sponsor_id;
                $change = $changeSponsor($member, $newSponsor, (string) $request->input('reason'), $admin->id);
                $audit->record($admin, 'members.change_sponsor', $member, ['sponsor_id' => $oldSponsorId], ['sponsor_id' => $newSponsor->id], $change->reason);
            });
        }, 'sponsor', 'Sponsor changed.');
    }

    /** @return array<string, mixed> */
    private function network(User $member, Genealogy $genealogy): array
    {
        $member->load(['package:id,name', 'position.parent:id,username']);
        $position = $member->position;

        return [
            'package' => $member->package?->name,
            'placed' => $position !== null,
            'parent' => $position?->parent ? ['id' => $position->parent->id, 'username' => $position->parent->username] : null,
            'leg' => $position?->leg,
            'depth' => $position?->depth,
            'pv' => $genealogy->pvFor([$member->id])[$member->id] ?? null,
        ];
    }

    private function admin(Request $request): Admin
    {
        /** @var Admin */
        return $request->user('admin');
    }

    /** Run an action; a refused action comes back as an error under $field. */
    private function attempt(callable $action, string $field, string $done): RedirectResponse
    {
        try {
            $action();
        } catch (InvalidArgumentException $e) {
            return back()->withErrors([$field => $e->getMessage()]);
        }

        return back()->with('status', $done);
    }
}
