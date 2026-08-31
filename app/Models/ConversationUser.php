<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ConversationUser extends Pivot
{
    protected function casts(): array
    {
        return [
            'last_read_at' => 'datetime',
        ];
    }
}
