<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

abstract class AppNotification extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', WebPushChannel::class];
    }

    /**
     * Every notification here already shapes itself as {message, url} for
     * toArray() — reuse that instead of making each subclass repeat itself
     * for the push payload too.
     */
    public function toWebPush(object $notifiable, self $notification): WebPushMessage
    {
        $data = $this->toArray($notifiable);

        return (new WebPushMessage)
            ->title(config('app.name'))
            ->body($data['message'])
            ->icon('/favicon-48x48.png')
            ->data(['url' => $data['url'] ?? null]);
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
