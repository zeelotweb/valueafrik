<?php

namespace App\Concerns;

use App\Models\Bookmark;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasBookmarks
{
    public function bookmarks(): MorphMany
    {
        return $this->morphMany(Bookmark::class, 'bookmarkable');
    }

    /**
     * Uses withExists('bookmarks as user_bookmarked', ...) when the caller
     * eager-loaded it — falls back to a live query otherwise.
     */
    public function isBookmarkedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if (array_key_exists('user_bookmarked', $this->attributes)) {
            return (bool) $this->attributes['user_bookmarked'];
        }

        return $this->bookmarks()->where('user_id', $user->id)->exists();
    }
}
