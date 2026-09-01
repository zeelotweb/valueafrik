<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveSession extends Model
{
    public const TYPE_CALL = 'call';

    public const TYPE_STREAM = 'stream';

    public const STATUS_LIVE = 'live';

    public const STATUS_ENDED = 'ended';

    protected $fillable = [
        'host_id',
        'room_name',
        'title',
        'type',
        'status',
        'started_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function isLive(): bool
    {
        return $this->status === self::STATUS_LIVE;
    }

    /**
     * Calls are open-mic for everyone in the room; streams only let the
     * host publish audio/video, everyone else just subscribes.
     */
    public function canPublish(User $user): bool
    {
        if ($this->type === self::TYPE_STREAM) {
            return $user->id === $this->host_id;
        }

        return true;
    }
}
