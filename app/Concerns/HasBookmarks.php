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

    public function isBookmarkedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->bookmarks()->where('user_id', $user->id)->exists();
    }
}
