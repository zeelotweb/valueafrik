<?php

namespace App\Notifications;

use App\Models\Community;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PromotedToMonitor extends Notification
{
    use Queueable;

    public function __construct(public Community $community)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'message' => "You've been made a monitor of \"{$this->community->name}\".",
            'url' => route('communities.show', $this->community),
        ];
    }
}
