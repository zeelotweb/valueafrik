<?php

namespace App\Notifications;

use App\Models\Community;

class PromotedToMonitor extends AppNotification
{
    public function __construct(public Community $community)
    {
    }

    public function toArray(object $notifiable): array
    {
        return [
            'message' => "You've been made a monitor of \"{$this->community->name}\".",
            'url' => route('communities.show', $this->community),
        ];
    }
}
