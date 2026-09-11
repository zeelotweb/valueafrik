<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Cashier\Billable;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use NotificationChannels\WebPush\HasPushSubscriptions;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use Billable, HasApiTokens, HasFactory, HasPushSubscriptions, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * A username is assigned automatically at creation, never through mass
     * assignment — this is the one place it happens, regardless of which
     * path created the user (password registration, Google, Facebook, a
     * factory in tests), so none of those call sites need to know about it.
     */
    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if (! $user->username) {
                $user->username = static::generateUniqueUsername($user->name);
            }
        });
    }

    private static function generateUniqueUsername(string $name): string
    {
        $base = Str::slug($name) ?: 'user';
        $username = $base;
        $suffix = 1;

        while (static::where('username', $username)->exists()) {
            $suffix++;
            $username = "{$base}-{$suffix}";
        }

        return $username;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'onboarded_at' => 'datetime',
            'banned_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function hasCompletedOnboarding(): bool
    {
        return $this->onboarded_at !== null;
    }

    public function completeOnboarding(): void
    {
        $this->forceFill(['onboarded_at' => now()])->save();
    }

    /**
     * Platform-wide moderator — distinct from a community owner/monitor,
     * which only has authority inside their own community. Granted via
     * `php artisan admin:grant {email}`, never through any in-app UI.
     */
    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    /**
     * A platform-wide suspension — separate from being removed from a
     * single community. A banned user is signed out on their next request
     * (see EnsureNotBanned) rather than merely hidden from feeds.
     */
    public function isBanned(): bool
    {
        return $this->banned_at !== null;
    }

    public function ban(string $reason): void
    {
        $this->forceFill(['banned_at' => now(), 'ban_reason' => $reason])->save();
    }

    public function unban(): void
    {
        $this->forceFill(['banned_at' => null, 'ban_reason' => null])->save();
    }

    /**
     * Heartbeat-based presence — see TrackLastSeen. Deliberately a wider
     * window than the middleware's own write-throttle, so a user isn't
     * flickered "offline" between two heartbeats.
     */
    public function isOnline(): bool
    {
        return $this->last_seen_at !== null
            && $this->last_seen_at->gt(now()->subSeconds(config('calls.online_threshold_seconds')));
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    public function languages(): BelongsToMany
    {
        return $this->belongsToMany(Language::class);
    }

    public function heritages(): BelongsToMany
    {
        return $this->belongsToMany(Heritage::class);
    }

    public function interests(): BelongsToMany
    {
        return $this->belongsToMany(Interest::class);
    }

    /**
     * Users this user follows.
     */
    public function following(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'follows', 'follower_id', 'following_id')->withTimestamps();
    }

    /**
     * Users following this user.
     */
    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'follows', 'following_id', 'follower_id')->withTimestamps();
    }

    public function isFollowing(User $user): bool
    {
        return $this->following()->whereKey($user->id)->exists();
    }

    /**
     * Users this user has blocked.
     */
    public function blocking(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'blocks', 'blocker_id', 'blocked_id')->withTimestamps();
    }

    /**
     * Users who have blocked this user.
     */
    public function blockedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'blocks', 'blocked_id', 'blocker_id')->withTimestamps();
    }

    public function hasBlocked(User $user): bool
    {
        return $this->blocking()->whereKey($user->id)->exists();
    }

    public function isBlockedBy(User $user): bool
    {
        return $this->blockedBy()->whereKey($user->id)->exists();
    }

    /**
     * True if either side has blocked the other — the check every
     * enforcement point (follow, message, Culture Sprint matching) uses,
     * since a block is meant to cut off contact in both directions.
     */
    public function hasBlockRelationWith(User $user): bool
    {
        return $this->hasBlocked($user) || $this->isBlockedBy($user);
    }

    /**
     * Blocking also severs any existing follow in both directions — it
     * would defeat the point of a block to let it coexist with a follow.
     */
    public function block(User $user): void
    {
        abort_if($this->id === $user->id, 403);

        $this->blocking()->syncWithoutDetaching($user->id);

        $this->following()->detach($user->id);
        $this->followers()->detach($user->id);
    }

    public function unblock(User $user): void
    {
        $this->blocking()->detach($user->id);
    }

    public function wallPosts(): HasMany
    {
        return $this->hasMany(WallPost::class);
    }

    public function bridgePostInvitesSent(): HasMany
    {
        return $this->hasMany(BridgePost::class, 'initiator_id');
    }

    public function bridgePostInvitesReceived(): HasMany
    {
        return $this->hasMany(BridgePost::class, 'partner_id');
    }

    public function liveSessions(): HasMany
    {
        return $this->hasMany(LiveSession::class, 'host_id');
    }

    public function ownedCommunities(): HasMany
    {
        return $this->hasMany(Community::class, 'owner_id');
    }

    /**
     * Communities this user is a member of (including ones they own).
     */
    public function communities(): BelongsToMany
    {
        return $this->belongsToMany(Community::class, 'community_user')
            ->withPivot(['role', 'status', 'points'])
            ->withTimestamps();
    }

    /**
     * How many communities this user is currently allowed to own, based on their follower count.
     */
    public function communitySlotLimit(): int
    {
        $followerCount = $this->followers()->count();

        $limit = 1;

        foreach (config('communities.creation_milestones') as $threshold => $slots) {
            if ($followerCount >= $threshold) {
                $limit = $slots;
            }
        }

        return $limit;
    }

    public function canCreateCommunity(): bool
    {
        return $this->ownedCommunities()->count() < $this->communitySlotLimit();
    }

    public function communityPosts(): HasMany
    {
        return $this->hasMany(CommunityPost::class);
    }

    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'conversation_user')
            ->using(ConversationUser::class)
            ->withPivot('last_read_at')
            ->withTimestamps();
    }

    public function unreadConversationsCount(): int
    {
        return $this->conversations()
            ->with('latestMessage')
            ->whereHas('latestMessage', fn ($query) => $query->where('user_id', '!=', $this->id))
            ->get()
            ->filter(function ($conversation) {
                $lastRead = $conversation->pivot->last_read_at;

                return ! $lastRead || $lastRead->lt($conversation->latestMessage->created_at);
            })
            ->count();
    }

    /**
     * Whether this user and another share no declared heritage — used to award
     * a bonus for connections that actually cross a cultural line, not just
     * any connection.
     */
    public function isCrossHeritageWith(User $other): bool
    {
        $mine = $this->heritages()->pluck('heritages.id');
        $theirs = $other->heritages()->pluck('heritages.id');

        return $mine->isNotEmpty() && $theirs->isNotEmpty() && $mine->intersect($theirs)->isEmpty();
    }

    public function bridgeScoreEvents(): HasMany
    {
        return $this->hasMany(BridgeScoreEvent::class);
    }

    public function bridgeScore(): int
    {
        return (int) $this->bridgeScoreEvents()->sum('points');
    }

    public function hasEarnedBridgeScoreFor(string $reason): bool
    {
        return $this->bridgeScoreEvents()->where('reason', $reason)->exists();
    }

    public function awardBridgeScore(string $reason, ?Model $subject = null): BridgeScoreEvent
    {
        $event = new BridgeScoreEvent([
            'user_id' => $this->id,
            'points' => config("bridge_score.points.{$reason}"),
            'reason' => $reason,
        ]);

        if ($subject) {
            $event->subject()->associate($subject);
        }

        $event->save();

        return $event;
    }

    /**
     * The highest badge threshold this user has crossed, or null if none yet.
     *
     * @return array{key: string, name: string}|null
     */
    public function bridgeBadge(): ?array
    {
        $score = $this->bridgeScore();
        $earned = null;

        foreach (config('bridge_score.badges') as $threshold => $badge) {
            if ($score >= $threshold) {
                $earned = $badge;
            }
        }

        return $earned;
    }

    /**
     * Whether this user is allowed to request collaborating on someone
     * else's live stream (a second publisher alongside the host) — gated
     * behind demonstrated engagement or a paid subscription, not open to
     * every viewer. See config/streams.php for the threshold itself.
     */
    public function canCollaborateOnStreams(): bool
    {
        return $this->bridgeScore() >= config('streams.collaboration_bridge_score_threshold')
            || $this->subscribed();
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
