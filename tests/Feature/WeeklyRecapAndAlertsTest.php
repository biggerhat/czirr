<?php

use App\Models\Chore;
use App\Models\ChoreAssignment;
use App\Models\ChoreCompletion;
use App\Models\FamilyMember;
use App\Models\Reward;
use App\Models\User;
use App\Notifications\PushAlert;
use App\Notifications\WeeklyRecap;
use Illuminate\Support\Facades\Notification;

test('weekly recap reports leaders and is sent to opted-in users', function () {
    Notification::fake();
    // Pin to midweek: on SQLite a date stored with a time sorts after the bare end-of-week
    // date string, so a Sunday run would silently drop that day's completion (MySQL is fine).
    $this->travelTo(now()->startOfWeek()->addDays(2)->setHour(12));
    $user = createAdminUser();
    $member = FamilyMember::where('linked_user_id', $user->id)->first();
    $chore = Chore::factory()->create(['user_id' => $user->id, 'points' => 7]);
    $assignment = ChoreAssignment::factory()->create(['chore_id' => $chore->id, 'family_member_id' => $member->id, 'day_of_week' => 1]);
    ChoreCompletion::create([
        'chore_assignment_id' => $assignment->id, 'family_member_id' => $member->id,
        'completed_date' => now()->toDateString(), 'points_earned' => 7,
    ]);

    $this->artisan('recap:send --force')->assertSuccessful();

    Notification::assertSentTo($user, WeeklyRecap::class, fn ($n) => $n->recap['chores_done'] === 1
        && $n->recap['leaders'][0]['points'] === 7);

    Notification::fake();
    $user->update(['notification_preferences' => ['weekly_recap' => false]]);
    $this->artisan('recap:send --force');
    Notification::assertNothingSent();
});

test('weekly recap respects the sunday evening window', function () {
    Notification::fake();
    createAdminUser();

    $this->travelTo(now()->next('Wednesday')->setHour(18));
    $this->artisan('recap:send');

    Notification::assertNothingSent();
});

test('parents are alerted to reward requests and kids to the decision', function () {
    Notification::fake();
    $parent = createAdminUser();

    $kidUser = User::factory()->create();
    $kidUser->assignRole('child');
    $kid = FamilyMember::factory()->create([
        'user_id' => $parent->id, 'name' => 'Kiddo', 'nickname' => null, 'linked_user_id' => $kidUser->id,
    ]);
    $chore = Chore::factory()->create(['user_id' => $parent->id, 'points' => 50]);
    $a = ChoreAssignment::factory()->create(['chore_id' => $chore->id, 'family_member_id' => $kid->id, 'day_of_week' => 1]);
    ChoreCompletion::create(['chore_assignment_id' => $a->id, 'family_member_id' => $kid->id, 'completed_date' => now()->toDateString(), 'points_earned' => 50]);
    $reward = Reward::factory()->create(['user_id' => $parent->id, 'cost' => 20]);
    // The fake only records notifications that have a deliverable channel.
    $parent->updatePushSubscription('https://push.example.com/parent', 'p', 'a');
    $kidUser->updatePushSubscription('https://push.example.com/kid', 'p', 'a');

    $res = $this->actingAs($kidUser)->postJson(route('rewards.redeem', $reward), ['family_member_id' => $kid->id])->assertCreated();
    Notification::assertSentTo($parent, PushAlert::class, fn ($n) => str_contains($n->body, 'Kiddo'));
    Notification::assertNotSentTo($kidUser, PushAlert::class);

    $this->actingAs($parent)->patchJson(route('rewards.resolve', $res->json('id')), ['status' => 'approved'])->assertOk();
    Notification::assertSentTo($kidUser, PushAlert::class, fn ($n) => str_contains($n->title, 'approved'));
});

test('push alerts only use web push for opted-in subscribed users', function () {
    $user = createAdminUser();
    $alert = new PushAlert('t', 'b');

    expect($alert->via($user))->toBe([]);

    $user->updatePushSubscription('https://push.example.com/x', 'p', 'a');
    expect($alert->via($user->fresh()))->toHaveCount(1);

    $user->update(['notification_preferences' => ['alerts_push' => false]]);
    expect($alert->via($user->fresh()))->toBe([]);
});
