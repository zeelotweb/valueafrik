<?php

namespace App\Models;

use App\Concerns\HasHashtags;
use App\Concerns\HasMentions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Comment extends Model
{
    use HasHashtags, HasMentions;

    protected $fillable = [
        'user_id',
        'parent_id',
        'body',
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

    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Null for a top-level comment; set for a reply.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Comment::class, 'parent_id')->latest();
    }

    public function votes(): HasMany
    {
        return $this->hasMany(CommentVote::class);
    }

    /**
     * Uses withCount('votes as upvotes_count', ...) when the caller
     * eager-loaded it — falls back to a live query otherwise.
     */
    public function upvotesCount(): int
    {
        if (array_key_exists('upvotes_count', $this->attributes)) {
            return (int) $this->attributes['upvotes_count'];
        }

        return $this->votes()->where('type', CommentVote::TYPE_UP)->count();
    }

    public function downvotesCount(): int
    {
        if (array_key_exists('downvotes_count', $this->attributes)) {
            return (int) $this->attributes['downvotes_count'];
        }

        return $this->votes()->where('type', CommentVote::TYPE_DOWN)->count();
    }

    /**
     * Uses an eager-loaded my_vote_type subselect when the caller added it
     * — falls back to a live query otherwise.
     */
    public function myVote(?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        if (array_key_exists('my_vote_type', $this->attributes)) {
            return $this->attributes['my_vote_type'];
        }

        return $this->votes()->where('user_id', $user->id)->value('type');
    }

    /**
     * One vote per user per comment — voting the other way replaces rather
     * than stacks; voting the same way again removes it.
     */
    public function voteAs(User $user, string $type): void
    {
        $existing = $this->votes()->where('user_id', $user->id)->first();

        if ($existing && $existing->type === $type) {
            $existing->delete();

            return;
        }

        if ($existing) {
            $existing->update(['type' => $type]);

            return;
        }

        $this->votes()->create(['user_id' => $user->id, 'type' => $type]);
    }
}
