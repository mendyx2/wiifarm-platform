<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The audit log, read only (specification section 11: it cannot be edited or
 * deleted from the panel). Filter by admin, action, record and date.
 */
class AuditLogController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $filters = $request->validate([
            'admin' => ['nullable', 'integer'],
            'action' => ['nullable', 'string', 'max:80'],
            'subject_type' => ['nullable', 'string', 'max:80'],
            'subject_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $entries = AuditLog::with('admin:id,username,name')
            ->when($filters['admin'] ?? null, fn (Builder $q, $id) => $q->where('admin_id', $id))
            ->when($filters['action'] ?? null, fn (Builder $q, $action) => $q->where('action', $action))
            ->when($filters['subject_type'] ?? null, fn (Builder $q, $type) => $q->where('subject_type', $type))
            ->when($filters['subject_id'] ?? null, fn (Builder $q, $id) => $q->where('subject_id', $id))
            ->when($filters['from'] ?? null, fn (Builder $q, $from) => $q->where('created_at', '>=', date('Y-m-d 00:00:00', strtotime($from))))
            ->when($filters['to'] ?? null, fn (Builder $q, $to) => $q->where('created_at', '<=', date('Y-m-d 23:59:59', strtotime($to))))
            ->latest('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (AuditLog $e) => [
                'id' => $e->id,
                'at' => $e->created_at->toDateTimeString(),
                'admin' => $e->admin ? ['id' => $e->admin->id, 'username' => $e->admin->username] : null,
                'action' => $e->action,
                'subject_type' => $e->subject_type,
                'subject_id' => $e->subject_id,
                'old_values' => $e->old_values,
                'new_values' => $e->new_values,
                'note' => $e->note,
                'ip_address' => $e->ip_address,
            ]);

        return Inertia::render('admin/audit', [
            'entries' => $entries,
            'filters' => (object) array_filter($filters, fn ($v) => $v !== null && $v !== ''),
            'admins' => Admin::orderBy('username')->get(['id', 'username']),
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
            'subjectTypes' => AuditLog::query()->whereNotNull('subject_type')->distinct()->orderBy('subject_type')->pluck('subject_type'),
        ]);
    }
}
