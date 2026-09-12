<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fires when the sender deletes a message "for everyone" — the other
 * participant's open thread swaps that message for the redacted payload
 * live, the same way a brand new MessageSent gets pushed in.
 */
class MessageDeletedForEveryone implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Message $message) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('conversation.'.$this->message->conversation_id)];
    }

    public function broadcastAs(): string
    {
        return 'MessageDeletedForEveryone';
    }

    public function broadcastWith(): array
    {
        return $this->message->toBroadcastArray();
    }
}
