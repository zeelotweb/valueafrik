<?php

namespace App\Notifications;

use App\Models\BridgePost;

class BridgePostAccepted extends AppNotification
{
    public function __construct(public BridgePost $bridgePost) {}

    public function toArray(object $notifiable): array
    {
        return [
            'message' => __(':name accepted your Bridge Post invite on ":theme". Add your side.', ['name' => $this->bridgePost->partner->name, 'theme' => $this->bridgePost->theme]),
            'url' => route('profile.show', $this->bridgePost->initiator),
        ];
    }
}
