<?php

namespace App\Notifications;

use App\Models\LiveSession;

class MissedCall extends AppNotification
{
    public function __construct(public LiveSession $session)
    {
    }

    public function toArray(object $notifiable): array
    {
        $message = $this->session->ended_reason === LiveSession::REASON_OFFLINE
            ? "{$this->session->host->name} tried to call you while you were offline."
            : "You missed a call from {$this->session->host->name}.";

        return [
            'message' => $message,
            'url' => route('live.show', $this->session),
        ];
    }
}
