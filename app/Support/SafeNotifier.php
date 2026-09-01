<?php

namespace App\Support;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;

class SafeNotifier
{
    /**
     * Send a notification without letting a broken broadcast connection take
     * down the action that triggered it — the database record still saves;
     * only the real-time push is best-effort. Mirrors the same lesson learned
     * the hard way with message broadcasting in production.
     *
     * @param  \Illuminate\Notifications\Notifiable|iterable  $notifiables
     */
    public static function send($notifiables, Notification $notification): void
    {
        try {
            NotificationFacade::send($notifiables, $notification);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
