<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\KycSubmission;
use App\Services\Members\Kyc;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The member's KYC page (specification section 2).
 */
class KycController extends Controller
{
    public function edit(Request $request, Kyc $kyc): Response
    {
        $user = $request->user();
        $latest = KycSubmission::where('user_id', $user->id)->latest('id')->first();

        return Inertia::render('settings/kyc', [
            'status' => $user->kyc_status,
            'fields' => array_map(fn ($f) => array_intersect_key($f, array_flip(['key', 'label', 'type', 'required', 'options', 'hint'])), $kyc->fields()),
            'latest' => $latest ? [
                'id' => $latest->id,
                'status' => $latest->status,
                'submitted_at' => $latest->created_at->toDateTimeString(),
                'rejection_reason' => $latest->rejection_reason,
                'answers' => collect($latest->fields)->map(fn ($f) => [
                    'label' => $f['label'],
                    'type' => $f['type'],
                    'value' => $f['type'] === 'file' ? null : ($latest->answers[$f['key']] ?? null),
                    'url' => $f['type'] === 'file' && isset($latest->answers[$f['key']])
                        ? route('kyc.file', [$latest->id, $f['key']]) : null,
                ])->values(),
            ] : null,
        ]);
    }

    public function store(Request $request, Kyc $kyc): RedirectResponse
    {
        $request->validate($kyc->rules(), [], collect($kyc->fields())->mapWithKeys(fn ($f) => ['answers.'.$f['key'] => $f['label']])->all());

        try {
            // all() includes the uploaded files alongside the typed answers
            $kyc->submit($request->user(), $request->all()['answers'] ?? []);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['kyc' => $e->getMessage()]);
        }

        return to_route('kyc.edit');
    }

    /**
     * A KYC file, shown only to its owner (admins get their own route in phase 2d).
     */
    public function file(Request $request, KycSubmission $submission, string $field): StreamedResponse
    {
        abort_unless($submission->user_id === $request->user()->id, 404);
        abort_unless(in_array($field, $submission->fileKeys(), true) && isset($submission->answers[$field]), 404);

        return Storage::disk(Kyc::DISK)->response($submission->answers[$field], headers: [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
