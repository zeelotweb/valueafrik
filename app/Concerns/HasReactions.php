<?php

namespace App\Concerns;

use App\Models\Reaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasReactions
{
    public function reactions(): MorphMany
    {
        return $this->morphMany(Reaction::class, 'reactable');
    }

    /**
     * Uses withCount('reactions') when the caller eager-loaded it (the
     * listing pages do, to avoid a query per post) — falls back to a live
     * query otherwise so nothing breaks where it isn't eager-loaded.
     */
    public function reactionsCount(): int
    {
        if (array_key_exists('reactions_count', $this->attributes)) {
            return (int) $this->attributes['reactions_count'];
        }

        return $this->reactions()->count();
    }

    /**
     * Uses withExists('reactions as user_reacted', ...) when the caller
     * eager-loaded it — falls back to a live query otherwise.
     */
    public function isReactedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if (array_key_exists('user_reacted', $this->attributes)) {
            return (bool) $this->attributes['user_reacted'];
        }

        return $this->reactions()->where('user_id', $user->id)->exists();
    }
}
