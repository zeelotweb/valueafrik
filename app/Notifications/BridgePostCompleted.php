<?php

namespace App\Notifications;

use App\Models\BridgePost;
use App\Models\User;

class BridgePostCompleted extends AppNotification
{
    public function __construct(public BridgePost $bridgePost, public User $completedBy)
    {
    }

    public function toArray(object $notifiable): array
    {
        return [
            'message' => "{$this->completedBy->name} added their side to your Bridge Post on \"{$this->bridgePost->theme}\" — it's live.",
            'url' => route('profile.show', $this->completedBy),
        ];
    }
}
