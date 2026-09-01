<?php

namespace App\Notifications;

use App\Models\Community;

class CommunityJoinApproved extends AppNotification
{
    public function __construct(public Community $community)
    {
    }

    public function toArray(object $notifiable): array
    {
        return [
            'message' => "You're in — your request to join \"{$this->community->name}\" was approved.",
            'url' => route('communities.show', $this->community),
        ];
    }
}
