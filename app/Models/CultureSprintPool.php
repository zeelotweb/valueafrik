<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

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
     * The matching rules shared by both the plain lookup and the atomic
     * claim below. Both topic and region come straight from what each side
     * typed into the intent form — never from declared heritage, language,
     * or interests, which drive Discover and the rest of the platform, not
     * this queue. Topic is a hard, exact key. Region is a two-way courtesy
     * *between the two forms*: leaving it blank means "anyone," and picking
     * one only rules out a candidate who picked a different one — a
     * candidate who also left theirs blank still qualifies.
     */
    private static function matchingQuery(User $user, string $topic, ?string $region): Builder
    {
        $onlineSince = now()->subSeconds((int) config('calls.online_threshold_seconds'));

        $blockedUserIds = $user->blocking()->pluck('users.id')
            ->merge($user->blockedBy()->pluck('users.id'));

        return self::query()
            ->where('user_id', '!=', $user->id)
            ->whereNotIn('user_id', $blockedUserIds)
            ->where('topic', $topic)
            ->whereHas('user', fn ($q) => $q->where('last_seen_at', '>', $onlineSince))
            ->when($region, fn ($q) => $q->where(
                fn ($q2) => $q2->whereNull('region')->orWhere('region', $region)
            ))
            ->oldest('created_at');
    }

    /**
     * The longest-waiting other online person waiting in the same topic
     * line. A pure read — does not claim or remove anything, so it's safe
     * to call more than once. Production matching should use
     * claimWaitingPartnerFor() instead, which closes the race this alone
     * can't: two searchers calling this at the same instant can both see
     * the same waiting row before either removes it.
     */
    public static function findWaitingPartnerFor(User $user, string $topic, ?string $region): ?User
    {
        return self::matchingQuery($user, $topic, $region)->with('user')->first()?->user;
    }

    /**
     * Same matching rules as findWaitingPartnerFor(), but atomically locks
     * the candidate row and removes both pool rows in one transaction, so
     * two people searching at the same instant can never both "win" the
     * same waiting partner — the second transaction's locked read simply
     * finds nothing once the first has committed and deleted the row.
     */
    public static function claimWaitingPartnerFor(User $user, string $topic, ?string $region): ?User
    {
        return DB::transaction(function () use ($user, $topic, $region) {
            $candidate = self::matchingQuery($user, $topic, $region)->lockForUpdate()->with('user')->first();

            if (! $candidate) {
                return null;
            }

            $partner = $candidate->user;

            self::where('user_id', $partner->id)->delete();
            self::where('user_id', $user->id)->delete();

            return $partner;
        });
    }
}
