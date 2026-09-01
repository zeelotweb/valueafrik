<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

abstract class AppNotification extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * A single, consistent event name across every notification type, so the
     * client only needs one Echo listener regardless of which of these fires.
     *
     * Both methods matter here: Laravel's BroadcastNotificationCreated event
     * only picks up a custom wire event name via broadcastAs() — broadcastType()
     * alone just labels the payload's "type" field, it doesn't rename the event.
     */
    public function broadcastType(): string
    {
        return 'notification.created';
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }
}
