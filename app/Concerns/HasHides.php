<?php

namespace App\Concerns;

use App\Models\PostHide;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasHides
{
    public function hides(): MorphMany
    {
        return $this->morphMany(PostHide::class, 'hideable');
    }

    public function isHiddenFor(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->hides()->where('user_id', $user->id)->exists();
    }

    /**
     * Per-viewer and idempotent — hiding twice (a double-click, two tabs)
     * just no-ops on the second attempt instead of 500ing, same pattern as
     * the other unique-constraint-backed toggles in this app.
     */
    public function hideFor(User $user): void
    {
        try {
            $this->hides()->create(['user_id' => $user->id]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            // Already hidden for this viewer — nothing left to do.
        }
    }
}
