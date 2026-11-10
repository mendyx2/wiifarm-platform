<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $status
 * @property array<string, string> $answers
 * @property list<array<string, mixed>> $fields
 * @property ?string $rejection_reason
 */
class KycSubmission extends Model
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'fields' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return list<string> the keys of the answers that are files */
    public function fileKeys(): array
    {
        return array_values(array_map(fn ($f) => $f['key'], array_filter($this->fields, fn ($f) => $f['type'] === 'file')));
    }
}
