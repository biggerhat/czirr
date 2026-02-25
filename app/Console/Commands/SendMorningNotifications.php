<?php

namespace App\Console\Commands;

use App\Enums\NotificationType;
use App\Enums\Permission;
use App\Models\User;
use App\Notifications\BillsDueNotification;
use App\Notifications\BirthdayReminderNotification;
use App\Notifications\ChoreReminderNotification;
use App\Notifications\MealPlanNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendMorningNotifications extends Command
{
    protected $signature = 'notifications:morning';

    protected $description = 'Send morning batch notifications (bills, chores, meals, birthdays)';

    public function handle(): void
    {
        $today = Carbon::today();
        $dayOfWeek = $today->dayOfWeek;

        User::whereHas('pushSubscriptions')
            ->with('notificationPreferences')
            ->chunk(100, function ($users) use ($today, $dayOfWeek) {
                foreach ($users as $user) {
                    $this->sendBillsNotification($user, $today);
                    $this->sendChoreNotification($user, $dayOfWeek);
                    $this->sendMealPlanNotification($user, $today);
                    $this->sendBirthdayNotification($user, $today);
                }
            });

        $this->info('Morning notifications sent.');
    }

    private function sendBillsNotification(User $user, Carbon $today): void
    {
        if (! $user->can(Permission::BudgetingView->value)) {
            return;
        }
        if (! $user->wantsPushNotification(NotificationType::BillsDue)) {
            return;
        }

        $owner = $user->familyOwner();
        $bills = $owner->bills()
            ->where('is_active', true)
            ->get()
            ->filter(fn (\App\Models\Bill $bill) => $bill->isDueInRange($today, $today));

        if ($bills->isEmpty()) {
            return;
        }

        $user->notify(new BillsDueNotification($bills));
    }

    private function sendChoreNotification(User $user, int $dayOfWeek): void
    {
        if (! $user->can(Permission::ChoresView->value)) {
            return;
        }
        if (! $user->wantsPushNotification(NotificationType::ChoreReminders)) {
            return;
        }

        $owner = $user->familyOwner();

        // Find the family member record for this user
        $linkedMember = $user->linkedFamilyMember();
        $ownMember = \App\Models\FamilyMember::where('user_id', $user->id)
            ->where('linked_user_id', $user->id)
            ->first();

        $memberIds = collect([$linkedMember?->id, $ownMember?->id])->filter()->unique();

        if ($memberIds->isEmpty()) {
            return;
        }

        $assignments = \App\Models\ChoreAssignment::whereIn('family_member_id', $memberIds)
            ->where('day_of_week', $dayOfWeek)
            ->with('chore')
            ->whereHas('chore', fn ($q) => $q->where('user_id', $owner->id)->where('is_active', true))
            ->get();

        if ($assignments->isEmpty()) {
            return;
        }

        $user->notify(new ChoreReminderNotification($assignments));
    }

    private function sendMealPlanNotification(User $user, Carbon $today): void
    {
        if (! $user->can(Permission::MealPlansView->value)) {
            return;
        }
        if (! $user->wantsPushNotification(NotificationType::MealPlan)) {
            return;
        }

        $owner = $user->familyOwner();
        $meals = $owner->mealPlanEntries()
            ->whereDate('date', $today)
            ->with('recipe')
            ->get();

        if ($meals->isEmpty()) {
            return;
        }

        $user->notify(new MealPlanNotification($meals));
    }

    private function sendBirthdayNotification(User $user, Carbon $today): void
    {
        if (! $user->can(Permission::ContactsView->value)) {
            return;
        }
        if (! $user->wantsPushNotification(NotificationType::BirthdayReminders)) {
            return;
        }

        $owner = $user->familyOwner();
        $contacts = $owner->contacts()
            ->whereNotNull('date_of_birth')
            ->whereMonth('date_of_birth', $today->month)
            ->whereDay('date_of_birth', $today->day)
            ->get();

        if ($contacts->isEmpty()) {
            return;
        }

        $user->notify(new BirthdayReminderNotification($contacts));
    }
}
