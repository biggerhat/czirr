<?php

namespace App\Observers;

use App\Models\Event;
use Illuminate\Support\Facades\Cache;

class EventObserver
{
    public function created(Event $event): void
    {
        $this->bustCache($event);
    }

    public function updated(Event $event): void
    {
        $this->bustCache($event);
    }

    public function deleted(Event $event): void
    {
        $this->bustCache($event);
    }

    private function bustCache(Event $event): void
    {
        $key = "calendar:v:{$event->user_id}";

        if (Cache::increment($key) === false) {
            Cache::forever($key, 1);
        }
    }
}
