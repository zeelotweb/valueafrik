<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A "pointer," not a session — a row here just means someone has signaled
 * they want to be matched right now. No LiveKit room, no token, nothing
 * active exists until this row is consumed by a match.
 */
class CultureSprintPool extends Model
{
    protected $table = 'culture_sprint_pool';

    protected $fillable = [
        'user_id',
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
     * The longest-waiting other person still actually online, preferring
     * someone who shares at least one language — otherwise "share about
     * your culture" can hit a wall immediately with a total stranger.
     * Falls back to anyone waiting if no language overlap is available.
     */
    public static function findWaitingPartnerFor(User $user): ?User
    {
        $onlineSince = now()->subSeconds((int) config('calls.online_threshold_seconds'));
        $myLanguageIds = $user->languages()->pluck('languages.id');

        $query = fn () => self::query()
            ->where('user_id', '!=', $user->id)
            ->whereHas('user', fn ($q) => $q->where('last_seen_at', '>', $onlineSince))
            ->with('user');

        if ($myLanguageIds->isNotEmpty()) {
            $withSharedLanguage = $query()
                ->whereHas('user.languages', fn ($q) => $q->whereIn('languages.id', $myLanguageIds))
                ->oldest('created_at')
                ->first();

            if ($withSharedLanguage) {
                return $withSharedLanguage->user;
            }
        }

        return $query()->oldest('created_at')->first()?->user;
    }
}
