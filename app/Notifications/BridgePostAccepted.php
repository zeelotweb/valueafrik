<?php

namespace App\Notifications;

use App\Models\BridgePost;

class BridgePostAccepted extends AppNotification
{
    public function __construct(public BridgePost $bridgePost)
    {
    }

    public function toArray(object $notifiable): array
    {
        return [
            'message' => "{$this->bridgePost->partner->name} accepted your Bridge Post invite on \"{$this->bridgePost->theme}\". Add your side.",
            'url' => route('profile.show', $this->bridgePost->initiator),
        ];
    }
}
