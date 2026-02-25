<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\TestPushNotification;
use Illuminate\Console\Command;

class SendTestPush extends Command
{
    protected $signature = 'push:test {userId}';

    protected $description = 'Send a test push notification to a user';

    public function handle(): void
    {
        $user = User::findOrFail($this->argument('userId'));

        if ($user->pushSubscriptions()->count() === 0) {
            $this->error('User has no push subscriptions.');

            return;
        }

        $user->notify(new TestPushNotification);

        $this->info('Test notification sent to '.$user->name.'.');
    }
}
