<?php

namespace App\Models\Support;

use App\Models\Admin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One message in a support ticket, from the member or an admin.
 *
 * @property int $id
 * @property int $ticket_id
 * @property ?int $admin_id
 * @property string $body
 * @property ?string $attachment_path
 * @property ?string $attachment_name
 * @property Carbon $created_at
 */
class SupportMessage extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    /** For the page: who wrote it, when, and the attachment's download link. */
    public function toRow(string $fileRoute): array
    {
        return [
            'id' => $this->id, 'from_wiifarm' => $this->admin_id !== null, 'author' => $this->admin_id ? 'Wiifarm support' : null,
            'admin' => $this->admin?->name, 'body' => $this->body, 'at' => $this->created_at->toDateTimeString(),
            'attachment' => $this->attachment_path ? ['name' => $this->attachment_name, 'url' => route($fileRoute, [$this->ticket_id, $this->id])] : null,
        ];
    }
}
