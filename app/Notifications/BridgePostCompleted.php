<?php

namespace App\Notifications;

use App\Models\BridgePost;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BridgePostCompleted extends Notification
{
    use Queueable;

    public function __construct(public BridgePost $bridgePost, public User $completedBy)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'message' => "{$this->completedBy->name} added their side to your Bridge Post on \"{$this->bridgePost->theme}\" — it's live.",
            'url' => route('profile.show', $this->completedBy),
        ];
    }
}
