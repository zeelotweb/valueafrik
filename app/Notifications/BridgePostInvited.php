<?php

namespace App\Notifications;

use App\Models\BridgePost;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BridgePostInvited extends Notification
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
            'message' => "{$this->bridgePost->initiator->name} invited you to a Bridge Post on \"{$this->bridgePost->theme}\".",
            'url' => route('dashboard'),
        ];
    }
}
