<?php

namespace App\Models;

use App\Concerns\HasBookmarks;
use App\Concerns\HasComments;
use App\Concerns\HasHashtags;
use App\Concerns\HasHides;
use App\Concerns\HasMentions;
use App\Concerns\HasReactions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WallPost extends Model
{
    use HasBookmarks, HasComments, HasHashtags, HasHides, HasMentions, HasReactions, SoftDeletes;

    public const VISIBILITY_PUBLIC = 'public';

    public const VISIBILITY_FOLLOWERS_ONLY = 'followers_only';

    public const VISIBILITY_PRIVATE = 'private';

    protected $fillable = [
        'user_id',
        'body',
        'visibility',
        'edited_at',
    ];

    protected function casts(): array
    {
        return [
            'edited_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    public function url(): string
    {
        return route('profile.show', $this->user);
    }

    /**
     * Mirrors Community::canView() — public is universal, followers-only
     * requires actually following the author (the author themself always
     * passes), and private is nobody but the author.
     */
    public function canView(User $viewer): bool
    {
        if ($viewer->id === $this->user_id) {
            return true;
        }

        return match ($this->visibility) {
            self::VISIBILITY_FOLLOWERS_ONLY => $viewer->isFollowing($this->user),
            self::VISIBILITY_PRIVATE => false,
            default => true,
        };
    }
}
