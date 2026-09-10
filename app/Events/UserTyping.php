<?php

namespace App\Events;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Purely ephemeral — nothing is persisted, this just pings the other
 * participant's open thread so it can show "X is typing…" for a few
 * seconds. Same channel MessageSent already broadcasts on.
 */
class UserTyping implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Conversation $conversation, public User $user)
    {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('conversation.'.$this->conversation->id)];
    }

    public function broadcastAs(): string
    {
        return 'UserTyping';
    }

    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
        ];
    }
}
