<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Cache;

class Conversation extends Model
{
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_user')
            ->using(ConversationUser::class)
            ->withPivot('last_read_at')
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    /**
     * Find the 1:1 conversation between two users, creating one if it doesn't exist yet.
     *
     * Locked per pair — two concurrent calls (a double-click, or two tabs)
     * would otherwise both pass the "does it exist" check before either
     * creates one, producing two separate conversations for the same pair
     * and splitting their message history across them.
     */
    public static function between(User $a, User $b): self
    {
        $lockKey = 'conversation-between:'.min($a->id, $b->id).':'.max($a->id, $b->id);

        return Cache::lock($lockKey, 10)->block(5, function () use ($a, $b) {
            $conversation = static::query()
                ->whereHas('participants', fn ($query) => $query->whereKey($a->id))
                ->whereHas('participants', fn ($query) => $query->whereKey($b->id))
                ->withCount('participants')
                ->get()
                ->firstWhere('participants_count', 2);

            if ($conversation) {
                return $conversation;
            }

            $conversation = static::create();
            $conversation->participants()->attach([$a->id, $b->id]);

            return $conversation;
        });
    }
}
