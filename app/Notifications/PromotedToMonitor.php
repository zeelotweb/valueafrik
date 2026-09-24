<?php

namespace App\Notifications;

use App\Models\Community;

class PromotedToMonitor extends AppNotification
{
    public function __construct(public Community $community) {}

    public function toArray(object $notifiable): array
    {
        return [
            'message' => __('You\'ve been made a monitor of ":community".', ['community' => $this->community->name]),
            'url' => route('communities.show', $this->community),
        ];
    }
}
