<?php

namespace App\Events;

use App\Models\LiveSession;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fires when a viewer requests to collaborate on a stream, or the host
 * approves/removes one — to both the host's and that specific viewer's
 * private channels, so the host's pending-requests list and the
 * requester's own "request to collaborate" button both update live without
 * a page reload.
 */
class StreamCollaborationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public LiveSession $session,
        public User $collaborator,
        public string $status,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('App.Models.User.'.$this->session->host_id),
            new PrivateChannel('App.Models.User.'.$this->collaborator->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'StreamCollaborationUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->session->id,
            'collaborator_id' => $this->collaborator->id,
            'collaborator_name' => $this->collaborator->name,
            'status' => $this->status,
        ];
    }
}
