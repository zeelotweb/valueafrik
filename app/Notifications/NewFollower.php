<?php

namespace App\Notifications;

use App\Models\User;

class NewFollower extends AppNotification
{
    public function __construct(public User $follower) {}

    public function toArray(object $notifiable): array
    {
        return [
            'message' => __(':name started following you.', ['name' => $this->follower->name]),
            'url' => route('profile.show', $this->follower),
        ];
    }
}
