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
     * line. Region is a two-way courtesy, not a queue key: if I asked for
     * "someone from Asia," only a candidate whose own heritage is actually
     * Asian qualifies — and if *they* asked for a region, I have to satisfy
     * theirs too, so nobody's stated preference gets silently ignored just
     * because the other side didn't set one. Within whatever's left,
     * shared interests or language are a soft nudge, not a requirement —
     * the topic line is usually thin enough that hard-requiring them would
     * mean waiting forever.
     */
    public static function findWaitingPartnerFor(User $user, string $topic, ?string $region): ?User
    {
        $onlineSince = now()->subSeconds((int) config('calls.online_threshold_seconds'));
        $myHeritageRegions = $user->heritages()->pluck('heritages.region')->filter()->unique()->values();
        $myLanguageIds = $user->languages()->pluck('languages.id');
        $myInterestIds = $user->interests()->pluck('interests.id');

        $base = fn () => self::query()
            ->where('user_id', '!=', $user->id)
            ->where('topic', $topic)
            ->whereHas('user', fn ($q) => $q->where('last_seen_at', '>', $onlineSince))
            ->when($region, fn ($q) => $q->whereHas(
                'user.heritages',
                fn ($q2) => $q2->where('heritages.region', $region)
            ))
            ->where(function ($q) use ($myHeritageRegions) {
                $q->whereNull('region');

                if ($myHeritageRegions->isNotEmpty()) {
                    $q->orWhereIn('region', $myHeritageRegions);
                }
            })
            ->with('user');

        if ($myInterestIds->isNotEmpty() || $myLanguageIds->isNotEmpty()) {
            $preferred = $base()
                ->where(function ($q) use ($myInterestIds, $myLanguageIds) {
                    if ($myInterestIds->isNotEmpty()) {
                        $q->orWhereHas('user.interests', fn ($q2) => $q2->whereIn('interests.id', $myInterestIds));
                    }

                    if ($myLanguageIds->isNotEmpty()) {
                        $q->orWhereHas('user.languages', fn ($q2) => $q2->whereIn('languages.id', $myLanguageIds));
                    }
                })
                ->oldest('created_at')
                ->first();

            if ($preferred) {
                return $preferred->user;
            }
        }

        return $base()->oldest('created_at')->first()?->user;
    }
}
