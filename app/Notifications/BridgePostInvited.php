<?php

namespace App\Notifications;

use App\Models\BridgePost;

class BridgePostInvited extends AppNotification
{
    public function __construct(public BridgePost $bridgePost)
    {
    }

    public function toArray(object $notifiable): array
    {
        return [
            'message' => "{$this->bridgePost->initiator->name} invited you to a Bridge Post on \"{$this->bridgePost->theme}\".",
            'url' => route('dashboard'),
        ];
    }
}
