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
 * Broadcast whenever $reader catches up on a conversation, so the sender's
 * own open thread can flip their sent messages to "Seen" without a reload.
 */
class MessagesRead implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Conversation $conversation, public User $reader)
    {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('conversation.'.$this->conversation->id)];
    }

    public function broadcastAs(): string
    {
        return 'MessagesRead';
    }

    public function broadcastWith(): array
    {
        return [
            'reader_id' => $this->reader->id,
        ];
    }
}
