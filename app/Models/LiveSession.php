<?php

namespace App\Models;

use App\Notifications\LiveCallStarted;
use App\Support\SafeNotifier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

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

    /**
     * Start a 1:1 call and let the other person know it's waiting for them —
     * the only way they'd otherwise find out is by happening to visit the
     * room URL directly.
     */
    public static function startCallWith(User $host, User $invitee): self
    {
        abort_if($host->id === $invitee->id, 403);

        $session = self::create([
            'host_id' => $host->id,
            'room_name' => (string) Str::uuid(),
            'type' => self::TYPE_CALL,
            'status' => self::STATUS_LIVE,
            'started_at' => now(),
        ]);

        SafeNotifier::send($invitee, new LiveCallStarted($session));

        return $session;
    }

    public static function startStream(User $host, ?string $title = null): self
    {
        return self::create([
            'host_id' => $host->id,
            'room_name' => (string) Str::uuid(),
            'title' => $title,
            'type' => self::TYPE_STREAM,
            'status' => self::STATUS_LIVE,
            'started_at' => now(),
        ]);
    }
}
