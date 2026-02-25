<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class BirthdayReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private Collection $contacts,
    ) {}

    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        $names = $this->contacts->map(fn ($c) => $c->first_name.' '.$c->last_name);
        $body = $names->count() === 1
            ? $names->first()."'s birthday is today!"
            : $names->implode(', ').' have birthdays today!';

        return (new WebPushMessage)
            ->title('Birthday Reminder')
            ->body($body)
            ->icon('/pwa-192x192.png')
            ->badge('/pwa-64x64.png')
            ->tag('birthdays-'.now()->toDateString())
            ->data(['url' => '/contacts']);
    }
}
