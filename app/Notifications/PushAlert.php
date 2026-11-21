<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/** A short, push-only alert (e.g. "Maya asked for a reward"). */
class PushAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $body,
        public string $url = '/dashboard',
        public string $tag = 'alert',
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $notifiable->notificationPreference('alerts_push') && $notifiable->pushSubscriptions()->exists()
            ? [WebPushChannel::class]
            : [];
    }

    public function toWebPush(object $notifiable, mixed $notification = null): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->title)
            ->icon('/pwa-192x192.png')
            ->body($this->body)
            ->tag($this->tag)
            ->data(['url' => $this->url]);
    }
}
