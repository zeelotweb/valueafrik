<?php

namespace App\Models;

use App\Events\CallStatusUpdated;
use App\Events\StreamCollaborationUpdated;
use App\Jobs\ExpireRingingCall;
use App\Notifications\LiveCallStarted;
use App\Notifications\MissedCall;
use App\Support\SafeNotifier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class LiveSession extends Model
{
    public const TYPE_CALL = 'call';

    public const TYPE_STREAM = 'stream';

    public const TYPE_SPRINT = 'sprint';

    public const STATUS_RINGING = 'ringing';

    public const STATUS_LIVE = 'live';

    public const STATUS_ENDED = 'ended';

    public const STATUS_MISSED = 'missed';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_CANCELED = 'canceled';

    public const REASON_OFFLINE = 'offline';

    public const REASON_TIMEOUT = 'timeout';

    /**
     * Cap on how many *new* call sessions one host can start per minute —
     * redialing the same person is deduped separately above and doesn't
     * count against this; this guards against dialing many different people
     * in a burst (harassment / notification spam), not normal retrying.
     */
    private const MAX_NEW_CALLS_PER_MINUTE = 10;

    public const VISIBILITY_PUBLIC = 'public';

    public const VISIBILITY_FOLLOWERS = 'followers';

    protected $fillable = [
        'host_id',
        'callee_id',
        'room_name',
        'title',
        'visibility',
        'culture_word',
        'type',
        'status',
        'ended_reason',
        'started_at',
        'answered_at',
        'host_accepted_at',
        'callee_accepted_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'answered_at' => 'datetime',
            'host_accepted_at' => 'datetime',
            'callee_accepted_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function callee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'callee_id');
    }

    public function isLive(): bool
    {
        return $this->status === self::STATUS_LIVE;
    }

    public function isRinging(): bool
    {
        return $this->status === self::STATUS_RINGING;
    }

    /**
     * Ringing, and still inside the ring window — the boundary that decides
     * whether a ringer can still be shown/answered versus needing to be
     * self-healed to "missed" first. Checked on every read path (page loads,
     * the global ringer's mount) so a stale row never gets treated as live
     * just because the sweep job hasn't run yet.
     */
    public function isStillRinging(): bool
    {
        return $this->isRinging()
            && $this->started_at->addSeconds($this->ringWindowSeconds())->isFuture();
    }

    /**
     * Calls ring for a while — someone might be mid-task and need a moment
     * to notice. A sprint match is meant to feel instant, so its accept
     * window is deliberately much shorter.
     */
    private function ringWindowSeconds(): int
    {
        return (int) config($this->type === self::TYPE_SPRINT ? 'culture_sprints.accept_seconds' : 'calls.ring_seconds');
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [self::STATUS_ENDED, self::STATUS_MISSED, self::STATUS_DECLINED, self::STATUS_CANCELED], true);
    }

    public function otherParty(User $user): ?User
    {
        if ($user->id === $this->host_id) {
            return $this->callee;
        }

        if ($user->id === $this->callee_id) {
            return $this->host;
        }

        return null;
    }

    public function isParticipant(User $user): bool
    {
        return $user->id === $this->host_id || $user->id === $this->callee_id;
    }

    /**
     * Calls are open-mic for everyone in the room; a stream only lets the
     * host publish audio/video, plus anyone the host has approved to
     * collaborate — everyone else just subscribes.
     */
    public function canPublish(User $user): bool
    {
        if ($this->type === self::TYPE_STREAM) {
            return $user->id === $this->host_id || $this->isApprovedCollaborator($user);
        }

        return true;
    }

    public function collaborators(): HasMany
    {
        return $this->hasMany(LiveSessionCollaborator::class);
    }

    public function pendingCollaboratorRequests(): HasMany
    {
        return $this->collaborators()->whereNull('approved_at');
    }

    public function isApprovedCollaborator(User $user): bool
    {
        return $this->collaborators()->where('user_id', $user->id)->whereNotNull('approved_at')->exists();
    }

    public function hasRequestedCollaboration(User $user): bool
    {
        return $this->collaborators()->where('user_id', $user->id)->exists();
    }

    /**
     * A viewer asks to become a second publisher. Gated behind the same
     * viewing rules as watching at all, plus the platform-wide engagement/
     * paid threshold in User::canCollaborateOnStreams() — this is a
     * lightweight co-host slot, not something every viewer can grab.
     */
    public function requestCollaboration(User $user): LiveSessionCollaborator
    {
        abort_unless($this->type === self::TYPE_STREAM, 403);
        abort_if($user->id === $this->host_id, 403);
        abort_unless($this->canView($user), 403);
        abort_unless($user->canCollaborateOnStreams(), 403);

        $row = $this->collaborators()->firstOrCreate(
            ['user_id' => $user->id],
            ['requested_at' => now()],
        );

        self::broadcastCollaboration($this, $user, 'requested');

        return $row;
    }

    public function approveCollaborator(User $host, User $collaborator): void
    {
        abort_unless($host->id === $this->host_id, 403);

        $row = $this->collaborators()->where('user_id', $collaborator->id)->firstOrFail();
        $row->update(['approved_at' => now()]);

        self::broadcastCollaboration($this, $collaborator, 'approved');
    }

    /**
     * Denies a pending request or revokes an already-approved collaborator
     * — either way the row is just gone, since a denial carries nothing
     * worth remembering and the host can always re-approve a fresh request.
     */
    public function removeCollaborator(User $host, User $collaborator): void
    {
        abort_unless($host->id === $this->host_id, 403);

        $this->collaborators()->where('user_id', $collaborator->id)->delete();

        self::broadcastCollaboration($this, $collaborator, 'removed');
    }

    /**
     * Calls and sprints are inherently private — only a participant can
     * view them, same as before. Streams are the one type an audience
     * outside the participants can view at all, gated by the host's own
     * visibility choice: public (anyone) or followers-only.
     */
    public function canView(User $user): bool
    {
        if ($this->type !== self::TYPE_STREAM) {
            return $this->isParticipant($user);
        }

        if ($user->id === $this->host_id) {
            return true;
        }

        if ($this->visibility === self::VISIBILITY_FOLLOWERS) {
            return $user->isFollowing($this->host);
        }

        return true;
    }

    /**
     * Start a 1:1 call. If the invitee is online, it rings — a delayed job
     * registers a missed call if nobody answers in time. If they're offline,
     * there's nothing to ring, so it's recorded as missed immediately and
     * the caller sees that reflected the moment they land on the room page.
     *
     * Reuses an already-ringing/live call between the same two people
     * instead of stacking a duplicate on top of it (e.g. a doubled click).
     */
    public static function startCallWith(User $host, User $invitee): self
    {
        abort_if($host->id === $invitee->id, 403);
        abort_if($host->hasBlockRelationWith($invitee), 403);

        $existing = self::query()
            ->where('type', self::TYPE_CALL)
            ->whereIn('status', [self::STATUS_RINGING, self::STATUS_LIVE])
            ->where(function ($query) use ($host, $invitee) {
                $query->where(['host_id' => $host->id, 'callee_id' => $invitee->id])
                    ->orWhere(['host_id' => $invitee->id, 'callee_id' => $host->id]);
            })
            ->first();

        if ($existing) {
            return $existing;
        }

        $throttleKey = 'start-call:'.$host->id;
        abort_if(RateLimiter::tooManyAttempts($throttleKey, self::MAX_NEW_CALLS_PER_MINUTE), 429);
        RateLimiter::hit($throttleKey, 60);

        $online = $invitee->isOnline();

        $session = self::create([
            'host_id' => $host->id,
            'callee_id' => $invitee->id,
            'room_name' => (string) Str::uuid(),
            'type' => self::TYPE_CALL,
            'status' => $online ? self::STATUS_RINGING : self::STATUS_MISSED,
            'ended_reason' => $online ? null : self::REASON_OFFLINE,
            'started_at' => now(),
            'ended_at' => $online ? null : now(),
        ]);

        if ($online) {
            SafeNotifier::send($invitee, new LiveCallStarted($session));

            // Requires a persistent `queue:work` process on the server — see
            // Forge's Daemons for this site. Without it, this job just sits
            // in the jobs table and a ringing call never resolves to missed.
            ExpireRingingCall::dispatch($session)->delay(now()->addSeconds((int) config('calls.ring_seconds')));
        } else {
            SafeNotifier::send($invitee, new MissedCall($session));
        }

        self::broadcastStatus($session);

        return $session;
    }

    public static function startStream(User $host, ?string $title = null, string $visibility = self::VISIBILITY_PUBLIC): self
    {
        return self::create([
            'host_id' => $host->id,
            'room_name' => (string) Str::uuid(),
            'title' => $title,
            'visibility' => $visibility,
            'type' => self::TYPE_STREAM,
            'status' => self::STATUS_LIVE,
            'started_at' => now(),
        ]);
    }

    /**
     * Two people just got matched out of the pool. Unlike a call, neither
     * side has already consented to this specific pairing — both
     * independently accept via respondToSprint() before it goes live.
     */
    public static function startSprintMatch(User $a, User $b, string $cultureWord): self
    {
        $session = self::create([
            'host_id' => $a->id,
            'callee_id' => $b->id,
            'room_name' => (string) Str::uuid(),
            'type' => self::TYPE_SPRINT,
            'status' => self::STATUS_RINGING,
            'culture_word' => $cultureWord,
            'started_at' => now(),
        ]);

        self::broadcastStatus($session);

        return $session;
    }

    /**
     * "That went well — same topic, one more round." Unlike the original
     * pool match, the two people here already know exactly who they're
     * asking — closer to a call than a random pairing, so the requester's
     * own side is pre-accepted (they consented by asking) and only the
     * other person needs to respond via the normal respondToSprint() flow.
     */
    public static function requestRematch(self $completed, User $requester): self
    {
        abort_unless($completed->type === self::TYPE_SPRINT, 403);
        abort_unless($completed->status === self::STATUS_ENDED, 403);
        abort_unless($completed->isParticipant($requester), 403);

        $partner = $completed->otherParty($requester);

        abort_if(! $partner, 404);

        $requesterIsHost = $requester->id === $completed->host_id;

        $session = self::create([
            'host_id' => $requesterIsHost ? $requester->id : $partner->id,
            'callee_id' => $requesterIsHost ? $partner->id : $requester->id,
            'room_name' => (string) Str::uuid(),
            'type' => self::TYPE_SPRINT,
            'status' => self::STATUS_RINGING,
            'culture_word' => $completed->culture_word,
            'started_at' => now(),
            'host_accepted_at' => $requesterIsHost ? now() : null,
            'callee_accepted_at' => $requesterIsHost ? null : now(),
        ]);

        self::broadcastStatus($session);

        return $session;
    }

    /**
     * Either side accepting or declining a matched sprint. The room only
     * goes live once *both* have accepted — accepting alone just records
     * that side's readiness and waits.
     */
    public function respondToSprint(User $user, bool $accept): void
    {
        abort_unless($this->type === self::TYPE_SPRINT, 403);
        abort_unless($this->isParticipant($user), 403);

        if (! $this->isStillRinging()) {
            $this->expireIfStale();

            return;
        }

        if (! $accept) {
            $this->update(['status' => self::STATUS_DECLINED, 'ended_at' => now()]);
            self::broadcastStatus($this);

            return;
        }

        // Locked so two near-simultaneous accepts can't both read a stale
        // "other side hasn't accepted yet" snapshot and neither one ever
        // sees the transition to live — without the lock, an in-memory
        // read right after $this->update() can miss a concurrent write
        // that landed between this request's read and write.
        DB::transaction(function () use ($user) {
            $locked = self::query()->lockForUpdate()->findOrFail($this->id);

            $field = $user->id === $locked->host_id ? 'host_accepted_at' : 'callee_accepted_at';
            $locked->update([$field => now()]);

            if ($locked->host_accepted_at && $locked->callee_accepted_at) {
                $locked->update(['status' => self::STATUS_LIVE, 'answered_at' => now()]);
            }

            $this->setRawAttributes($locked->fresh()->getAttributes());
        });

        self::broadcastStatus($this);
    }

    /**
     * Both turns are up — ends the sprint and awards Bridge Score to both
     * sides for actually completing it (a bigger bonus when it happened to
     * cross a heritage line), same "engagement outweighs output" rule the
     * rest of scoring follows. Only a participant can trigger this — same
     * guard as respondToSprint()/endOrCancel(), since this is otherwise
     * callable by anyone who can reach a session ID.
     */
    public function completeSprint(User $user): void
    {
        abort_unless($this->type === self::TYPE_SPRINT, 403);
        abort_unless($this->isParticipant($user), 403);

        if (! $this->isLive()) {
            return;
        }

        $this->update(['status' => self::STATUS_ENDED, 'ended_at' => now()]);

        foreach ([$this->host, $this->callee] as $participant) {
            $participant->awardBridgeScore('culture_sprint_completed', $this);

            if ($this->host->isCrossHeritageWith($this->callee)) {
                $participant->awardBridgeScore('culture_sprint_cross_heritage_bonus', $this);
            }
        }

        self::broadcastStatus($this);
    }

    /**
     * The callee answers or declines a still-ringing call.
     */
    public function respondToRing(User $user, bool $accept): void
    {
        abort_unless($user->id === $this->callee_id, 403);

        if (! $this->isStillRinging()) {
            $this->expireIfStale();

            return;
        }

        $this->update($accept
            ? ['status' => self::STATUS_LIVE, 'answered_at' => now()]
            : ['status' => self::STATUS_DECLINED, 'ended_at' => now()]);

        self::broadcastStatus($this);
    }

    /**
     * Either party can end a call — while it's still ringing (a cancel) or
     * once it's live (a hangup) — and it ends for both sides immediately.
     * Streams stay host-only, enforced by the caller (the live.show page).
     */
    public function endOrCancel(User $user): void
    {
        abort_unless($this->isParticipant($user), 403);

        if ($this->isRinging()) {
            $this->update(['status' => self::STATUS_CANCELED, 'ended_at' => now()]);
        } elseif ($this->isLive()) {
            $this->update(['status' => self::STATUS_ENDED, 'ended_at' => now()]);
        } else {
            return;
        }

        self::broadcastStatus($this);
    }

    /**
     * Idempotent: marks a still-ringing call missed once its window has
     * passed. Called by the delayed job (the push path) and opportunistically
     * from every read path (the pull path, in case the job never ran) so the
     * two can never disagree for long.
     */
    public function expireIfStale(): bool
    {
        if (! $this->isRinging() || $this->isStillRinging()) {
            return false;
        }

        $this->update(['status' => self::STATUS_MISSED, 'ended_reason' => self::REASON_TIMEOUT, 'ended_at' => now()]);

        // A sprint match is a same-page, real-time-only interaction — if it
        // times out, whoever's still there sees it live via the broadcast.
        // There's no "you missed a call" moment worth an async notification
        // for a random match the way there is for someone calling you by name.
        if ($this->type === self::TYPE_CALL) {
            SafeNotifier::send($this->callee, new MissedCall($this));
        }

        self::broadcastStatus($this);

        return true;
    }

    private static function broadcastStatus(self $session): void
    {
        try {
            broadcast(new CallStatusUpdated($session));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private static function broadcastCollaboration(self $session, User $collaborator, string $status): void
    {
        try {
            broadcast(new StreamCollaborationUpdated($session, $collaborator, $status));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
