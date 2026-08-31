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
    use Billable, HasApiTokens, HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
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

    public function wallPosts(): HasMany
    {
        return $this->hasMany(WallPost::class);
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
