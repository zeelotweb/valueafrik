<?php

namespace App\Notifications;

use App\Models\BridgePost;
use App\Models\User;

class BridgePostCompleted extends AppNotification
{
    public function __construct(public BridgePost $bridgePost, public User $completedBy) {}

    public function toArray(object $notifiable): array
    {
        return [
            'message' => __(':name added their side to your Bridge Post on ":theme" — it\'s live.', ['name' => $this->completedBy->name, 'theme' => $this->bridgePost->theme]),
            'url' => route('profile.show', $this->completedBy),
        ];
    }
}
