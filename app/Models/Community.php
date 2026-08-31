<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Community extends Model
{
    use SoftDeletes;

    public const VISIBILITY_PUBLIC = 'public';

    public const VISIBILITY_PRIVATE = 'private';

    public const VISIBILITY_FOLLOWERS_ONLY = 'followers_only';

    public const PARTICIPATION_VIEW_ONLY = 'view_only';

    public const PARTICIPATION_POST = 'post';

    protected $fillable = [
        'owner_id',
        'name',
        'slug',
        'description',
        'visibility',
        'participation_level',
        'avatar_path',
        'cover_path',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'community_user')
            ->withPivot(['role', 'status', 'points'])
            ->withTimestamps();
    }

    /**
     * Members whose membership is active (i.e. not a pending private-join request).
     */
    public function activeMembers(): BelongsToMany
    {
        return $this->members()->wherePivot('status', 'active');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(CommunityPost::class);
    }

    public function monitorRequests(): HasMany
    {
        return $this->hasMany(CommunityMonitorRequest::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(CommunityReport::class);
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null;
    }

    public function coverUrl(): ?string
    {
        return $this->cover_path ? Storage::disk('public')->url($this->cover_path) : null;
    }

    public function membershipFor(User $user): ?object
    {
        return $this->members()->whereKey($user->id)->first()?->pivot;
    }

    public function roleFor(User $user): ?string
    {
        $membership = $this->membershipFor($user);

        return $membership?->status === 'active' ? $membership->role : null;
    }

    public function isMember(User $user): bool
    {
        return $this->roleFor($user) !== null;
    }

    public function hasPendingRequestFrom(User $user): bool
    {
        return $this->membershipFor($user)?->status === 'pending';
    }

    /**
     * Whether the given user is allowed to see this community's feed.
     */
    public function canView(User $user): bool
    {
        return match ($this->visibility) {
            self::VISIBILITY_PRIVATE => $this->isMember($user) || $user->id === $this->owner_id,
            default => true,
        };
    }

    /**
     * Whether the given user is eligible to join (separate from the cap check).
     */
    public function isJoinableBy(User $user): bool
    {
        if ($this->membershipFor($user) !== null) {
            return false;
        }

        return match ($this->visibility) {
            self::VISIBILITY_FOLLOWERS_ONLY => $this->owner->followers()->whereKey($user->id)->exists(),
            default => true,
        };
    }

    public function isFull(): bool
    {
        return $this->activeMembers()->count() >= config('communities.membership_cap');
    }

    public function canPost(User $user): bool
    {
        $role = $this->roleFor($user);

        if ($role === null) {
            return false;
        }

        if ($this->participation_level === self::PARTICIPATION_VIEW_ONLY) {
            return in_array($role, ['owner', 'monitor']);
        }

        return true;
    }

    public function canModerate(User $user): bool
    {
        return in_array($this->roleFor($user), ['owner', 'monitor']);
    }

    /**
     * How many monitor slots this community has earned, based on its current member count.
     */
    public function monitorSlotLimit(): int
    {
        $memberCount = $this->activeMembers()->count();

        $limit = 0;

        foreach (config('communities.monitor_milestones') as $threshold => $slots) {
            if ($memberCount >= $threshold) {
                $limit = $slots;
            }
        }

        return $limit;
    }

    public function monitorCount(): int
    {
        return $this->activeMembers()->wherePivot('role', 'monitor')->count();
    }

    public function canPromoteMonitor(): bool
    {
        return $this->monitorCount() < $this->monitorSlotLimit();
    }
}
