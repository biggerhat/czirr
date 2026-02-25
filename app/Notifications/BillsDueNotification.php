<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class BillsDueNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private Collection $bills,
    ) {}

    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        $names = $this->bills->map(fn ($bill) => $bill->name.' ($'.number_format((float) $bill->amount, 2).')');
        $body = $names->implode(', ');

        return (new WebPushMessage)
            ->title('Bills Due Today')
            ->body($body)
            ->icon('/pwa-192x192.png')
            ->badge('/pwa-64x64.png')
            ->tag('bills-due-'.now()->toDateString())
            ->data(['url' => '/budgeting']);
    }
}
