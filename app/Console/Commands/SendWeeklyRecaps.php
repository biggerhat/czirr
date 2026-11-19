<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\WeeklyRecap;
use App\Services\WeeklyRecapService;
use Illuminate\Console\Command;

class SendWeeklyRecaps extends Command
{
    /** Local hour on Sunday when recaps go out. */
    private const HOUR = 18;

    protected $signature = 'recap:send {--force : Ignore the day/hour check}';

    protected $description = 'Send the weekly family recap (Sunday evening, user-local time)';

    public function handle(WeeklyRecapService $service): int
    {
        $sent = 0;

        User::whereNotNull('email_verified_at')->each(function (User $user) use ($service, &$sent) {
            if (! $user->notificationPreference('weekly_recap')) {
                return;
            }

            $local = now()->setTimezone($user->timezone ?: 'UTC');
            if (! $this->option('force') && ! ($local->isSunday() && $local->hour === self::HOUR)) {
                return;
            }

            $recap = $service->build($user);
            if ($service->isEmpty($recap)) {
                return;
            }

            $user->notify(new WeeklyRecap($recap));
            $sent++;
        });

        $this->info("Sent {$sent} recap(s).");

        return self::SUCCESS;
    }
}
