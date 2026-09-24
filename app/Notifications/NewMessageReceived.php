<?php

namespace App\Notifications;

use App\Models\Message;

class NewMessageReceived extends AppNotification
{
    public function __construct(public Message $message) {}

    public function toArray(object $notifiable): array
    {
        return [
            'message' => __(':name sent you a message.', ['name' => $this->message->user->name]),
            'url' => route('messages.show', $this->message->conversation),
        ];
    }
}
