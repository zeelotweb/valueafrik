<?php

namespace App\Notifications;

use App\Models\LiveSession;

class MissedCall extends AppNotification
{
    public function __construct(public LiveSession $session) {}

    public function toArray(object $notifiable): array
    {
        $message = $this->session->ended_reason === LiveSession::REASON_OFFLINE
            ? __(':name tried to call you while you were offline.', ['name' => $this->session->host->name])
            : __('You missed a call from :name.', ['name' => $this->session->host->name]);

        return [
            'message' => $message,
            'url' => route('live.show', $this->session),
        ];
    }
}
