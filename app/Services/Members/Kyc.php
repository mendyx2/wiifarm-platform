<?php

namespace App\Services\Members;

use App\Models\KycSubmission;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * KYC (specification section 2): the member submits the form the admin set
 * (setting "kyc.fields"); an admin approves, or rejects with a reason, and the
 * member may then submit again. Files go to private storage (the "local" disk,
 * outside the public folder) and are only served through a signed-in route.
 */
class Kyc
{
    public const DISK = 'local';

    /** @return list<array<string, mixed>> */
    public function fields(): array
    {
        return Settings::get('kyc.fields');
    }

    /**
     * Validation rules for the current form.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $rules = [];
        foreach ($this->fields() as $f) {
            $r = [($f['required'] ?? false) ? 'required' : 'nullable'];
            $r = match ($f['type']) {
                'file' => [...$r, 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
                'select' => [...$r, 'string', Rule::in($f['options'] ?? [])],
                default => [...$r, 'string', 'max:255', ...(isset($f['pattern']) ? ['regex:/'.$f['pattern'].'/'] : [])],
            };
            $rules['answers.'.$f['key']] = $r;
        }

        return $rules;
    }

    /**
     * @param  array<string, string|UploadedFile|null>  $answers  already validated
     */
    public function submit(User $user, array $answers): KycSubmission
    {
        $fields = $this->fields();
        $stored = [];
        $saved = [];

        try {
            foreach ($fields as $f) {
                $value = $answers[$f['key']] ?? null;
                if ($value instanceof UploadedFile) {
                    $path = $value->storeAs('kyc/'.$user->id, Str::uuid().'.'.$value->guessExtension(), self::DISK);
                    $saved[] = $path;
                    $stored[$f['key']] = $path;
                } elseif ($value !== null && $value !== '') {
                    $stored[$f['key']] = trim((string) $value);
                }
            }

            return DB::transaction(function () use ($user, $stored, $fields) {
                // Checked on the locked row, so two submissions at the same moment cannot both pass.
                $locked = User::lockForUpdate()->findOrFail($user->id);
                if ($locked->kyc_status === 'pending') {
                    throw new InvalidArgumentException('Your documents are already being reviewed.');
                }
                if ($locked->kyc_status === 'verified') {
                    throw new InvalidArgumentException('Your identity is already verified.');
                }

                $submission = KycSubmission::create([
                    'user_id' => $user->id,
                    'status' => KycSubmission::PENDING,
                    'answers' => $stored,
                    'fields' => $fields,
                ]);
                $locked->forceFill(['kyc_status' => 'pending'])->save();
                $user->kyc_status = 'pending';

                return $submission;
            });
        } catch (\Throwable $e) {
            Storage::disk(self::DISK)->delete($saved); // no orphan files if the submission was refused or failed

            throw $e;
        }
    }

    public function approve(KycSubmission $submission, int $adminId): void
    {
        $this->decide($submission, $adminId, KycSubmission::APPROVED, null, 'verified');
    }

    public function reject(KycSubmission $submission, int $adminId, string $reason): void
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Give the member a reason, so they know what to fix.');
        }

        $this->decide($submission, $adminId, KycSubmission::REJECTED, trim($reason), 'unverified');
    }

    private function decide(KycSubmission $submission, int $adminId, string $status, ?string $reason, string $userStatus): void
    {
        DB::transaction(function () use ($submission, $adminId, $status, $reason, $userStatus) {
            $locked = KycSubmission::lockForUpdate()->findOrFail($submission->id);
            if ($locked->status !== KycSubmission::PENDING) {
                throw new InvalidArgumentException('This submission has already been reviewed.');
            }

            $locked->forceFill([
                'status' => $status,
                'rejection_reason' => $reason,
                'reviewed_by_admin_id' => $adminId,
                'reviewed_at' => now(),
            ])->save();

            $locked->user->forceFill(['kyc_status' => $userStatus])->save();
        });
    }
}
