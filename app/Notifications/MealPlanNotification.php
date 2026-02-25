<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class MealPlanNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private Collection $meals,
    ) {}

    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        $lines = $this->meals->map(fn ($meal) => ucfirst($meal->meal_type->value).': '.($meal->name ?? $meal->recipe?->name ?? 'TBD'));
        $body = $lines->implode(', ');

        return (new WebPushMessage)
            ->title("Today's Meal Plan")
            ->body($body)
            ->icon('/pwa-192x192.png')
            ->badge('/pwa-64x64.png')
            ->tag('meal-plan-'.now()->toDateString())
            ->data(['url' => '/meal-plans']);
    }
}
