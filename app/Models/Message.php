<?php

namespace App\Models;

use App\Concerns\HasReactions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Message extends Model
{
    use HasReactions;

    protected $fillable = [
        'conversation_id',
        'user_id',
        'body',
        'read_at',
        'deleted_for_everyone_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'deleted_for_everyone_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    public function hides(): HasMany
    {
        return $this->hasMany(MessageHide::class);
    }

    public function isHiddenFor(User $user): bool
    {
        return $this->hides()->where('user_id', $user->id)->exists();
    }

    /**
     * Removes this message from just $user's own view — the other
     * participant's copy of the conversation is completely unaffected, and
     * nothing is broadcast for it.
     */
    public function hideFor(User $user): void
    {
        $this->hides()->firstOrCreate(['user_id' => $user->id]);
    }

    public function isDeletedForEveryone(): bool
    {
        return $this->deleted_for_everyone_at !== null;
    }

    /**
     * Replaces this message's content for *both* participants — only the
     * original sender may do this, matching the common "delete for
     * everyone" convention (deleting someone else's message out from under
     * them, even in your own conversation, isn't something either side
     * should be able to do).
     */
    public function deleteForEveryone(User $user): void
    {
        abort_unless($user->id === $this->user_id, 403);

        $this->update(['deleted_for_everyone_at' => now()]);
    }

    /**
     * The single source of truth for a message's array shape — used both
     * for the initial page load and for the MessageSent/
     * MessageDeletedForEveryone broadcast payloads the *other* participant's
     * live thread pushes straight into its own message list with no
     * reshaping. One method, used everywhere, so those can't drift the way
     * they did before (see the git history on this method for that story).
     *
     * A message deleted for everyone never has its real body/media leave
     * this method at all, in either direction — not just hidden by the
     * Blade template — so there's no path (a fresh page load, a delayed
     * broadcast) that can still leak the original content afterward.
     */
    public function toBroadcastArray(): array
    {
        $this->loadMissing(['user.profile', 'media']);

        $deleted = $this->isDeletedForEveryone();

        return [
            'id' => $this->id,
            'body' => $deleted ? null : $this->body,
            'user_id' => $this->user_id,
            'user_name' => $this->user->name,
            'avatar_url' => $this->user->profile?->avatarUrl(),
            'created_at' => $this->created_at->toIso8601String(),
            'read_at' => $this->read_at?->toIso8601String(),
            'media' => $deleted ? [] : $this->media->map(fn ($media) => ['url' => $media->url(), 'thumbnail_url' => $media->thumbnailUrl()])->all(),
            'deleted_for_everyone' => $deleted,
        ];
    }
}
