<?php

namespace App\Notifications;

use App\Models\FamilyList;
use App\Models\FamilyListItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class ListItemAddedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private FamilyListItem $item,
        private FamilyList $list,
        private string $addedBy,
    ) {}

    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->addedBy.' added to '.$this->list->name)
            ->body($this->item->name)
            ->icon('/pwa-192x192.png')
            ->badge('/pwa-64x64.png')
            ->tag('list-item-'.$this->list->id)
            ->data(['url' => '/lists/'.$this->list->id]);
    }
}
