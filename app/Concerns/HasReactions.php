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

    /**
     * The specific type (Reaction::TYPE_LIKE or one of Reaction::EMOJIS) of
     * this user's reaction, or null if they haven't reacted. Only queries
     * when isReactedBy() is already true, so a feed of mostly-unreacted
     * posts doesn't pay for this on every card.
     */
    public function myReactionType(?User $user): ?string
    {
        if (! $this->isReactedBy($user)) {
            return null;
        }

        return $this->reactions()->where('user_id', $user->id)->value('type');
    }

    /**
     * One reaction per user per item, enforced by a DB unique constraint —
     * picking a new type replaces rather than stacks. Tapping the same
     * type you already have removes it (a toggle), shared by both the
     * heart button and the emoji picker so they can never disagree about
     * what "your reaction" currently is.
     */
    public function reactAs(User $user, string $type): void
    {
        // Every model using this trait (WallPost, CommunityPost, Message)
        // exposes user() as its author/sender — the same block check
        // follow/message/call already enforce on a profile, applied here so
        // a blocked pair can't reach each other through a reaction either.
        abort_if($user->hasBlockRelationWith($this->user), 403);

        $existing = $this->reactions()->where('user_id', $user->id)->first();

        if ($existing && $existing->type === $type) {
            $existing->delete();

            return;
        }

        if ($existing) {
            $existing->update(['type' => $type]);

            return;
        }

        $this->reactions()->create(['user_id' => $user->id, 'type' => $type]);

        // Scoped to this specific reactable — unreacting (above) never
        // claws the point back, so without this guard, react/unreact/react
        // repeatedly re-triggers the create branch and awards every time.
        // Once earned for this item it stays earned; it just doesn't
        // re-earn.
        if (! $user->hasEarnedBridgeScoreFor('reaction_given', $this)) {
            $user->awardBridgeScore('reaction_given', $this);
        }
    }
}
