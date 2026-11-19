<?php

use App\Models\Chore;
use App\Models\ChoreAssignment;
use App\Models\ChoreCompletion;
use App\Models\FamilyMember;
use App\Models\Reward;
use App\Models\RewardRedemption;
use App\Services\RewardService;

function earnPoints(int $ownerId, FamilyMember $member, int $points): void
{
    $chore = Chore::factory()->create(['user_id' => $ownerId, 'points' => $points]);
    $assignment = ChoreAssignment::factory()->create([
        'chore_id' => $chore->id,
        'family_member_id' => $member->id,
        'day_of_week' => 1,
    ]);
    ChoreCompletion::create([
        'chore_assignment_id' => $assignment->id,
        'family_member_id' => $member->id,
        'completed_date' => now()->toDateString(),
        'points_earned' => $points,
    ]);
}

test('rewards page renders and parents can create rewards', function () {
    $user = createAdminUser();

    $this->actingAs($user)->get(route('rewards.index'))->assertOk()
        ->assertInertia(fn ($p) => $p->component('rewards/Index')->where('can.manage', true));

    $this->actingAs($user)->postJson(route('rewards.store'), ['name' => 'Movie night', 'cost' => 20])
        ->assertCreated();

    expect(Reward::where('user_id', $user->id)->count())->toBe(1);
});

test('redeeming spends points and a denial refunds them', function () {
    $user = createAdminUser();
    $member = FamilyMember::where('linked_user_id', $user->id)->first();
    earnPoints($user->id, $member, 30);
    $reward = Reward::factory()->create(['user_id' => $user->id, 'cost' => 20]);
    $service = app(RewardService::class);

    expect($service->balances($user->id)[$member->id])->toBe(30);

    $res = $this->actingAs($user)->postJson(route('rewards.redeem', $reward), ['family_member_id' => $member->id])
        ->assertCreated();
    expect($service->balances($user->id)[$member->id])->toBe(10);

    $this->actingAs($user)->patchJson(route('rewards.resolve', $res->json('id')), ['status' => 'denied'])->assertOk();
    expect($service->balances($user->id)[$member->id])->toBe(30);
});

test('cannot redeem without enough points', function () {
    $user = createAdminUser();
    $member = FamilyMember::where('linked_user_id', $user->id)->first();
    $reward = Reward::factory()->create(['user_id' => $user->id, 'cost' => 20]);

    $this->actingAs($user)->postJson(route('rewards.redeem', $reward), ['family_member_id' => $member->id])
        ->assertStatus(422);
    expect(RewardRedemption::count())->toBe(0);
});

test('cannot touch another family\'s rewards', function () {
    $user = createAdminUser();
    $other = createAdminUser();
    $reward = Reward::factory()->create(['user_id' => $other->id]);

    $this->actingAs($user)->deleteJson(route('rewards.destroy', $reward))->assertForbidden();
});

test('my day shows today\'s chores for the linked member', function () {
    $user = createAdminUser();
    $member = FamilyMember::where('linked_user_id', $user->id)->first();
    $chore = Chore::factory()->create(['user_id' => $user->id]);
    ChoreAssignment::factory()->create([
        'chore_id' => $chore->id,
        'family_member_id' => $member->id,
        'day_of_week' => now('UTC')->dayOfWeek,
    ]);

    $this->actingAs($user)->get(route('my-day.index'))->assertOk()
        ->assertInertia(fn ($p) => $p->component('my-day/Index')
            ->where('member.id', $member->id)
            ->has('chores', 1)
            ->has('stats.balance'));
});
