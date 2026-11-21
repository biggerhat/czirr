<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class DailyDigest extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param array{date: string, chores: list<array>, meals: list<array>, events: list<array>, bills: list<array>, birthdays?: list<array>} $digest */
    public function __construct(public array $digest) {}

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

    public function toWebPush(object $notifiable, mixed $notification = null): WebPushMessage
    {
        $d = $this->digest;
        $parts = array_filter([
            $d['events'] ? count($d['events']).' event'.(count($d['events']) === 1 ? '' : 's') : null,
            $d['chores'] ? count($d['chores']).' chore'.(count($d['chores']) === 1 ? '' : 's').' to do' : null,
            ! empty($d['birthdays']) ? count($d['birthdays']).' birthday'.(count($d['birthdays']) === 1 ? '' : 's').' this week' : null,
            $d['bills'] ? count($d['bills']).' bill'.(count($d['bills']) === 1 ? '' : 's').' due' : null,
            $d['meals'] ? 'dinner: '.(collect($d['meals'])->firstWhere('type', 'Dinner')['name'] ?? 'planned') : null,
        ]);

        return (new WebPushMessage)
            ->title('Your day')
            ->icon('/pwa-192x192.png')
            ->body(implode(' · ', $parts))
            ->tag('daily-digest')
            ->data(['url' => '/dashboard']);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $d = $this->digest;
        $d['birthdays'] ??= [];
        $mail = (new MailMessage)
            ->subject('Your day — '.date('D, M j', strtotime($d['date'])))
            ->greeting('Good morning, '.$notifiable->name.'!');

        if ($d['events']) {
            $mail->line('**Events**');
            foreach ($d['events'] as $e) {
                $mail->line("- {$e['time']} — {$e['title']}");
            }
        }
        if ($d['chores']) {
            $mail->line('**Chores to do**');
            foreach ($d['chores'] as $c) {
                $mail->line('- '.$c['title'].($c['who'] ? " ({$c['who']})" : ''));
            }
        }
        if ($d['meals']) {
            $mail->line('**Meals**');
            foreach ($d['meals'] as $m) {
                $mail->line("- {$m['type']}: {$m['name']}");
            }
        }
        if ($d['bills']) {
            $mail->line('**Bills due**');
            foreach ($d['bills'] as $b) {
                $mail->line("- {$b['name']} (\${$b['amount']}) — {$b['due']}");
            }
        }

        if ($d['birthdays']) {
            $mail->line('**Birthdays coming up**');
            foreach ($d['birthdays'] as $b) {
                $mail->line("- {$b['name']} — {$b['when']}".($b['age'] ? " (turns {$b['age']})" : ''));
            }
        }

        return $mail->action('Open dashboard', url('/dashboard'))
            ->line('You can change these emails in Settings → Notifications.');
    }
}
