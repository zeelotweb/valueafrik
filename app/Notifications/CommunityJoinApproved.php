<?php

namespace App\Notifications;

use App\Models\Community;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CommunityJoinApproved extends Notification
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
            'message' => "You're in — your request to join \"{$this->community->name}\" was approved.",
            'url' => route('communities.show', $this->community),
        ];
    }
}
