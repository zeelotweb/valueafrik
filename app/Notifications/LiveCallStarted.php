<?php

namespace App\Notifications;

use App\Models\LiveSession;

class LiveCallStarted extends AppNotification
{
    public function __construct(public LiveSession $session)
    {
    }

    public function toArray(object $notifiable): array
    {
        return [
            'message' => "{$this->session->host->name} started a call with you.",
            'url' => route('live.show', $this->session),
        ];
    }
}
