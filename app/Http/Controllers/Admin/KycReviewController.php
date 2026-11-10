<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\KycSubmission;
use App\Services\Admin\Audit;
use App\Services\Members\Kyc;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * KYC review (specification sections 2 and 11): the list of submissions,
 * one submission with its documents, and approve or reject with a reason.
 */
class KycReviewController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->validate(['status' => ['nullable', Rule::in(['pending', 'approved', 'rejected', 'all'])]])['status'] ?? 'pending';

        $submissions = KycSubmission::with('user:id,username,first_name,last_name')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            // Oldest first while waiting, so members are served in turn; newest first otherwise.
            ->orderBy('id', $status === 'pending' ? 'asc' : 'desc')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (KycSubmission $s) => [
                'id' => $s->id,
                'status' => $s->status,
                'member' => ['id' => $s->user->id, 'username' => $s->user->username, 'name' => $s->user->name],
                'submitted_at' => $s->created_at->toDateTimeString(),
                'reviewed_at' => $s->reviewed_at?->toDateTimeString(),
            ]);

        return Inertia::render('admin/kyc/index', ['submissions' => $submissions, 'status' => $status]);
    }

    public function show(KycSubmission $submission): Response
    {
        $submission->load('user');
        $reviewer = $submission->reviewed_by_admin_id ? Admin::find($submission->reviewed_by_admin_id) : null;

        return Inertia::render('admin/kyc/show', [
            'submission' => [
                'id' => $submission->id,
                'status' => $submission->status,
                'submitted_at' => $submission->created_at->toDateTimeString(),
                'reviewed_at' => $submission->reviewed_at?->toDateTimeString(),
                'reviewed_by' => $reviewer?->username,
                'rejection_reason' => $submission->rejection_reason,
                'member' => [
                    'id' => $submission->user->id,
                    'username' => $submission->user->username,
                    'name' => $submission->user->name,
                    'email' => $submission->user->email,
                    'country' => $submission->user->country,
                ],
                'answers' => collect($submission->fields)->map(fn ($f) => [
                    'label' => $f['label'],
                    'type' => $f['type'],
                    'value' => $f['type'] === 'file' ? null : ($submission->answers[$f['key']] ?? null),
                    'url' => $f['type'] === 'file' && isset($submission->answers[$f['key']])
                        ? route('admin.kyc.file', [$submission->id, $f['key']]) : null,
                    'isImage' => $f['type'] === 'file' && isset($submission->answers[$f['key']])
                        && preg_match('/\.(jpe?g|png)$/i', $submission->answers[$f['key']]) === 1,
                ])->values(),
            ],
            'earlier' => KycSubmission::where('user_id', $submission->user_id)->where('id', '!=', $submission->id)
                ->latest('id')->get(['id', 'status', 'created_at', 'rejection_reason'])
                ->map(fn (KycSubmission $s) => ['id' => $s->id, 'status' => $s->status, 'submitted_at' => $s->created_at->toDateTimeString(), 'rejection_reason' => $s->rejection_reason]),
        ]);
    }

    public function file(KycSubmission $submission, string $field): StreamedResponse
    {
        abort_unless(in_array($field, $submission->fileKeys(), true) && isset($submission->answers[$field]), 404);

        return Storage::disk(Kyc::DISK)->response($submission->answers[$field], headers: [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function approve(Request $request, KycSubmission $submission, Kyc $kyc, Audit $audit): RedirectResponse
    {
        return $this->decide($request, $submission, fn (Admin $admin) => $kyc->approve($submission, $admin->id), $audit, 'kyc.approve', null, 'Approved. The member is now verified.');
    }

    public function reject(Request $request, KycSubmission $submission, Kyc $kyc, Audit $audit): RedirectResponse
    {
        $reason = (string) $request->validate(['reason' => ['required', 'string', 'max:250']], ['reason.required' => 'Give the member a reason, so they know what to fix.'])['reason'];

        return $this->decide($request, $submission, fn (Admin $admin) => $kyc->reject($submission, $admin->id, $reason), $audit, 'kyc.reject', $reason, 'Rejected. The member sees the reason and can submit again.');
    }

    private function decide(Request $request, KycSubmission $submission, callable $action, Audit $audit, string $auditAction, ?string $note, string $done): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');

        try {
            DB::transaction(function () use ($admin, $submission, $action, $audit, $auditAction, $note) {
                $action($admin);
                $fresh = $submission->fresh();
                $audit->record($admin, $auditAction, $fresh, ['status' => KycSubmission::PENDING], ['status' => $fresh->status], $note);
            });
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['kyc' => $e->getMessage()]);
        }

        return to_route('admin.kyc.index')->with('status', $done);
    }
}
