<?php

namespace App\Models\Messages;

use Illuminate\Database\Eloquent\Model;

/**
 * An admin's version of a message (specification section 12). Events with no
 * row use the defaults in config/messages.php.
 *
 * @property int $id
 * @property string $event
 * @property string $subject
 * @property string $body
 * @property bool $enabled
 */
class MessageTemplate extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }
}
