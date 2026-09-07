<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A "pointer," not a session — a row here just means someone has signaled
 * they want to be matched right now, and which line they're waiting in.
 * No LiveKit room, no token, nothing active exists until this row is
 * consumed by a match.
 */
class CultureSprintPool extends Model
{
    protected $table = 'culture_sprint_pool';

    protected $fillable = [
        'user_id',
        'topic',
        'region',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Rows nobody ever matched — left behind by someone who signaled
     * interest and then closed the tab. Swept opportunistically rather
     * than needing a scheduled job.
     */
    public static function pruneStale(): void
    {
        self::query()
            ->where('created_at', '<', now()->subMinutes((int) config('culture_sprints.pool_stale_minutes')))
            ->delete();
    }

    /**
     * The longest-waiting other online person waiting in the same topic
     * line. Both topic and region come straight from what each side typed
     * into the intent form — never from declared heritage, language, or
     * interests, which drive Discover and the rest of the platform, not
     * this queue. Topic is a hard, exact key. Region is a two-way courtesy
     * *between the two forms*: leaving it blank means "anyone," and
     * picking one only rules out a candidate who picked a different one —
     * a candidate who also left theirs blank still qualifies.
     */
    public static function findWaitingPartnerFor(User $user, string $topic, ?string $region): ?User
    {
        $onlineSince = now()->subSeconds((int) config('calls.online_threshold_seconds'));

        return self::query()
            ->where('user_id', '!=', $user->id)
            ->where('topic', $topic)
            ->whereHas('user', fn ($q) => $q->where('last_seen_at', '>', $onlineSince))
            ->when($region, fn ($q) => $q->where(
                fn ($q2) => $q2->whereNull('region')->orWhere('region', $region)
            ))
            ->with('user')
            ->oldest('created_at')
            ->first()?->user;
    }
}
