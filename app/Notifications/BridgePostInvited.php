<?php

namespace App\Notifications;

use App\Models\BridgePost;

class BridgePostInvited extends AppNotification
{
    public function __construct(public BridgePost $bridgePost) {}

    public function toArray(object $notifiable): array
    {
        return [
            'message' => __(':name invited you to a Bridge Post on ":theme".', ['name' => $this->bridgePost->initiator->name, 'theme' => $this->bridgePost->theme]),
            'url' => route('dashboard'),
        ];
    }
}
