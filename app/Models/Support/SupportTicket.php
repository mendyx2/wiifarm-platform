<?php

namespace App\Models\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A member's support ticket (specification section 11).
 *
 * @property int $id
 * @property int $user_id
 * @property string $subject
 * @property string $status
 * @property ?Carbon $last_message_at
 * @property ?Carbon $closed_at
 * @property Carbon $created_at
 */
class SupportTicket extends Model
{
    public const OPEN = 'open';           // waiting for Wiifarm

    public const ANSWERED = 'answered';   // waiting for the member

    public const CLOSED = 'closed';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime', 'closed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class, 'ticket_id')->orderBy('id');
    }
}
