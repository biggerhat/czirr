<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class ChoreReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private Collection $assignments,
    ) {}

    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        $names = $this->assignments->map(fn ($a) => $a->chore->name);
        $body = $names->implode(', ');

        return (new WebPushMessage)
            ->title('Chores for Today')
            ->body($body)
            ->icon('/pwa-192x192.png')
            ->badge('/pwa-64x64.png')
            ->tag('chores-'.now()->toDateString())
            ->data(['url' => '/chores']);
    }
}
