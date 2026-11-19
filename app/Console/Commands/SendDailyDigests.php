<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\DailyDigest;
use App\Services\DailyDigestService;
use Illuminate\Console\Command;

class SendDailyDigests extends Command
{
    protected $signature = 'digest:send {--force : Ignore the user\'s preferred hour}';

    protected $description = 'Send the daily digest to users whose local digest hour is now';

    public function handle(DailyDigestService $service): int
    {
        $sent = 0;

        User::whereNotNull('email_verified_at')->each(function (User $user) use ($service, &$sent) {
            if (! $user->notificationPreference('digest_email') && ! $user->notificationPreference('digest_push')) {
                return;
            }

            $localHour = now()->setTimezone($user->timezone ?: 'UTC')->hour;
            if (! $this->option('force') && $localHour !== (int) $user->notificationPreference('digest_hour')) {
                return;
            }

            // Users without a family yet have nothing to show.
            $digest = $service->build($user);
            if ($service->isEmpty($digest)) {
                return;
            }

            $user->notify(new DailyDigest($digest));
            $sent++;
        });

        $this->info("Sent {$sent} digest(s).");

        return self::SUCCESS;
    }
}
