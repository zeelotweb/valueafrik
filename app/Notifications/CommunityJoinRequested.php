<?php

namespace App\Notifications;

use App\Models\Community;
use App\Models\User;

class CommunityJoinRequested extends AppNotification
{
    public function __construct(public Community $community, public User $requester) {}

    public function toArray(object $notifiable): array
    {
        return [
            'message' => __(':name asked to join ":community".', ['name' => $this->requester->name, 'community' => $this->community->name]),
            'url' => route('communities.show', $this->community),
        ];
    }
}
