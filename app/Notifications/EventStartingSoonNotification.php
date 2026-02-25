<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class EventStartingSoonNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private array $eventData,
    ) {}

    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        $title = $this->eventData['title'] ?? 'Event';

        return (new WebPushMessage)
            ->title($title.' starts in 15 minutes')
            ->body('Tap to view your calendar')
            ->icon('/pwa-192x192.png')
            ->badge('/pwa-64x64.png')
            ->tag('event-'.$this->eventData['id'].'-'.$this->eventData['starts_at'])
            ->data(['url' => '/calendar']);
    }
}
