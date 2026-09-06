<?php

namespace App\Events;

use App\Models\LiveSession;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fires on every call state change — ringing, answered, declined, canceled,
 * missed, ended — to both participants' private channels. One event, one
 * client listener; whichever screen is open (global ringer, the outgoing
 * "calling…" waiting screen, or the room itself) reacts to whatever status
 * shows up.
 */
class CallStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public LiveSession $session)
    {
        $this->session->loadMissing(['host.profile', 'callee.profile']);
    }

    public function broadcastOn(): array
    {
        return array_filter([
            new PrivateChannel('App.Models.User.'.$this->session->host_id),
            $this->session->callee_id ? new PrivateChannel('App.Models.User.'.$this->session->callee_id) : null,
        ]);
    }

    public function broadcastAs(): string
    {
        return 'CallStatusUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->session->id,
            'status' => $this->session->status,
            'room_url' => route('live.show', $this->session),
            'host' => [
                'id' => $this->session->host_id,
                'name' => $this->session->host->name,
                'avatar_url' => $this->session->host->profile?->avatarUrl(),
            ],
            'callee' => $this->session->callee ? [
                'id' => $this->session->callee_id,
                'name' => $this->session->callee->name,
                'avatar_url' => $this->session->callee->profile?->avatarUrl(),
            ] : null,
        ];
    }
}
