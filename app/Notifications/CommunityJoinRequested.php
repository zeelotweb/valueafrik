<?php

namespace App\Notifications;

use App\Models\Community;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CommunityJoinRequested extends Notification
{
    use Queueable;

    public function __construct(public Community $community, public User $requester)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'message' => "{$this->requester->name} asked to join \"{$this->community->name}\".",
            'url' => route('communities.show', $this->community),
        ];
    }
}
