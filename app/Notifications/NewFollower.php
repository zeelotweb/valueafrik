<?php

namespace App\Notifications;

use App\Models\User;

class NewFollower extends AppNotification
{
    public function __construct(public User $follower)
    {
    }

    public function toArray(object $notifiable): array
    {
        return [
            'message' => "{$this->follower->name} started following you.",
            'url' => route('profile.show', $this->follower),
        ];
    }
}
