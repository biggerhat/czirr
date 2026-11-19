<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class WeeklyRecap extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param array{leaders: list<array{name: string, points: int}>, chores_done: int, bills_paid: int, bills_total: float, meals_next_week: int} $recap */
    public function __construct(public array $recap) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        $channels = [];
        if ($notifiable->notificationPreference('digest_email')) {
            $channels[] = 'mail';
        }
        if ($notifiable->notificationPreference('digest_push') && $notifiable->pushSubscriptions()->exists()) {
            $channels[] = WebPushChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $r = $this->recap;
        $mail = (new MailMessage)->subject('Your family week in review')->greeting('Happy Sunday, '.$notifiable->name.'!');

        if ($r['leaders']) {
            $mail->line('**Chore leaderboard**');
            foreach ($r['leaders'] as $i => $l) {
                $mail->line(($i + 1).". {$l['name']} — {$l['points']} pts");
            }
        }
        $mail->line("**{$r['chores_done']}** chores completed this week.");
        if ($r['bills_paid']) {
            $mail->line("**{$r['bills_paid']}** bills paid (\$".number_format($r['bills_total'], 2).').');
        }
        $mail->line($r['meals_next_week']
            ? "**{$r['meals_next_week']}** meals are planned for next week."
            : 'Nothing is planned for next week yet — a good time to plan meals!');

        return $mail->action('Open dashboard', url('/dashboard'));
    }

    public function toWebPush(object $notifiable, mixed $notification = null): WebPushMessage
    {
        $r = $this->recap;
        $top = $r['leaders'][0] ?? null;

        return (new WebPushMessage)
            ->title('Your family week in review')
            ->icon('/pwa-192x192.png')
            ->body(implode(' · ', array_filter([
                $top ? "{$top['name']} led with {$top['points']} pts" : null,
                "{$r['chores_done']} chores done",
                $r['meals_next_week'] ? null : 'plan next week’s meals',
            ])))
            ->tag('weekly-recap')
            ->data(['url' => '/scoreboard']);
    }
}
