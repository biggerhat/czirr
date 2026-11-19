<?php

use App\Models\Bill;
use App\Models\BudgetCategory;
use App\Models\Chore;
use App\Models\ChoreAssignment;
use App\Models\FamilyMember;
use App\Notifications\DailyDigest;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;

function digestUserWithChore(): array
{
    $user = createAdminUser();
    $member = FamilyMember::where('linked_user_id', $user->id)->first();
    $chore = Chore::factory()->create(['user_id' => $user->id, 'is_active' => true]);
    ChoreAssignment::factory()->create([
        'chore_id' => $chore->id,
        'family_member_id' => $member->id,
        'day_of_week' => Carbon::now('UTC')->dayOfWeek,
    ]);

    return [$user, $chore];
}

test('digest is sent to users whose local hour matches', function () {
    Notification::fake();
    [$user] = digestUserWithChore();
    $user->update(['timezone' => 'UTC', 'notification_preferences' => ['digest_hour' => now('UTC')->hour]]);

    $this->artisan('digest:send')->assertSuccessful();

    Notification::assertSentTo($user, DailyDigest::class, fn ($n) => count($n->digest['chores']) === 1);
});

test('digest is skipped when hour does not match or user opted out', function () {
    Notification::fake();
    [$user] = digestUserWithChore();
    $user->update(['notification_preferences' => ['digest_hour' => (now('UTC')->hour + 1) % 24]]);

    $this->artisan('digest:send');
    Notification::assertNothingSent();

    $user->update(['notification_preferences' => ['digest_email' => false]]);
    $this->artisan('digest:send --force');
    Notification::assertNothingSent();
});

test('digest is skipped when there is nothing to report', function () {
    Notification::fake();
    $user = createAdminUser();

    $this->artisan('digest:send --force');

    Notification::assertNothingSent();
});

test('upcoming unpaid bills appear in the digest', function () {
    Notification::fake();
    $user = createAdminUser();
    $category = BudgetCategory::factory()->create(['user_id' => $user->id]);
    Bill::factory()->create([
        'user_id' => $user->id,
        'budget_category_id' => $category->id,
        'is_active' => true,
        'frequency' => 'once',
        'start_date' => Carbon::today()->addDay()->format('Y-m-d'),
    ]);

    $this->artisan('digest:send --force');

    Notification::assertSentTo($user, DailyDigest::class, fn ($n) => count($n->digest['bills']) === 1);
});

test('notification settings can be updated', function () {
    $user = createAdminUser();

    $this->actingAs($user)->get(route('notifications.edit'))->assertOk();

    $this->actingAs($user)->patch(route('notifications.update'), [
        'timezone' => 'America/Chicago',
        'digest_email' => false,
        'digest_push' => true,
        'alerts_push' => true,
        'weekly_recap' => true,
        'digest_hour' => 6,
        'bills_due_days' => 5,
    ])->assertRedirect();

    $user->refresh();
    expect($user->timezone)->toBe('America/Chicago')
        ->and($user->notificationPreference('digest_email'))->toBeFalse()
        ->and($user->notificationPreference('digest_hour'))->toBe(6);
});

test('push subscriptions can be stored and removed', function () {
    $user = createAdminUser();
    $payload = [
        'endpoint' => 'https://push.example.com/abc123',
        'keys' => ['auth' => 'authkey', 'p256dh' => 'p256key'],
    ];

    $this->actingAs($user)->postJson(route('push.subscribe'), $payload)->assertOk();
    expect($user->pushSubscriptions()->count())->toBe(1);

    $this->actingAs($user)->deleteJson(route('push.unsubscribe'), ['endpoint' => $payload['endpoint']])->assertOk();
    expect($user->pushSubscriptions()->count())->toBe(0);
});

test('digest uses web push only for subscribed users who enabled it', function () {
    $user = createAdminUser();
    $notification = new DailyDigest(['date' => today()->toDateString(), 'chores' => [['title' => 'x', 'who' => null]], 'meals' => [], 'events' => [], 'bills' => []]);

    expect($notification->via($user))->toBe(['mail']);

    $user->updatePushSubscription('https://push.example.com/abc', 'p256', 'auth');
    expect($notification->via($user))->toContain(NotificationChannels\WebPush\WebPushChannel::class);

    $user->update(['notification_preferences' => ['digest_push' => false]]);
    expect($notification->via($user->fresh()))->toBe(['mail']);
});

test('upcoming birthdays appear in the digest', function () {
    Notification::fake();
    $user = createAdminUser();
    App\Models\Contact::factory()->create([
        'user_id' => $user->id,
        'first_name' => 'Grandma',
        'last_name' => 'Jo',
        'date_of_birth' => now()->addDays(3)->subYears(70)->toDateString(),
    ]);
    App\Models\Contact::factory()->create(['user_id' => $user->id, 'date_of_birth' => now()->addDays(30)->subYears(5)->toDateString()]);

    $this->artisan('digest:send --force');

    Notification::assertSentTo($user, DailyDigest::class, fn ($n) => count($n->digest['birthdays']) === 1
        && $n->digest['birthdays'][0]['name'] === 'Grandma Jo'
        && $n->digest['birthdays'][0]['age'] === 70);
});
