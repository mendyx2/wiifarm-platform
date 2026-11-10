<?php

namespace App\Services\Admin;

use App\Models\Admin;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Writes the audit log: who did what to which record, the values before and
 * after, when, and from which IP address (specification section 11).
 *
 *     $audit->record($admin, 'members.ban', $member, ['status' => 'active'], ['status' => 'banned'], 'Fraud');
 */
class Audit
{
    public function __construct(private readonly Request $request) {}

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public function record(?Admin $admin, string $action, ?Model $subject = null, ?array $old = null, ?array $new = null, ?string $note = null): AuditLog
    {
        return AuditLog::create([
            'admin_id' => $admin?->id,
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'old_values' => $old,
            'new_values' => $new,
            'note' => $note === null ? null : Str::limit($note, 250),
            'ip_address' => $this->request->ip(),
            'user_agent' => Str::limit((string) $this->request->userAgent(), 250, ''),
        ]);
    }
}
