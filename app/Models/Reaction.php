<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Reaction extends Model
{
    public const TYPE_LIKE = 'like';

    /**
     * The alternate reaction types offered by the emoji picker, distinct
     * from the dedicated heart (TYPE_LIKE) button. The stored type is the
     * emoji character itself — no separate name-to-glyph mapping to keep
     * in sync.
     *
     * @var list<string>
     */
    public const EMOJIS = ['😂', '😮', '😢', '😡', '🙏', '🔥'];

    protected $fillable = [
        'user_id',
        'type',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reactable(): MorphTo
    {
        return $this->morphTo();
    }
}
