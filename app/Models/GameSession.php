<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One person's game. 'type' picks the game and 'state' is that game's own
 * toArray()/fromArray() shape — this table doesn't know or care what's
 * inside it. 'opponent' is who's on the other side: today that's either the
 * same person passing the device ('human', hot-seat) or the computer;
 * 'online' — a real second account — is reserved for real multiplayer.
 */
class GameSession extends Model
{
    public const TYPE_AYO = 'ayo';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_FINISHED = 'finished';

    public const OPPONENT_HUMAN = 'human';

    public const OPPONENT_COMPUTER = 'computer';

    protected $fillable = [
        'user_id',
        'type',
        'opponent',
        'difficulty',
        'state',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'state' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @param Builder<GameSession> $query */
    public function scopeActiveOfType(Builder $query, int $userId, string $type): Builder
    {
        return $query->where('user_id', $userId)
            ->where('type', $type)
            ->where('status', self::STATUS_ACTIVE);
    }
}
