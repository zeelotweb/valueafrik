<?php

namespace App\Notifications;

use App\Models\BridgePost;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BridgePostAccepted extends Notification
{
    use Queueable;

    public function __construct(public BridgePost $bridgePost)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'message' => "{$this->bridgePost->partner->name} accepted your Bridge Post invite on \"{$this->bridgePost->theme}\". Add your side.",
            'url' => route('profile.show', $this->bridgePost->initiator),
        ];
    }
}
