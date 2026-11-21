<?php

use App\Models\Chore;
use App\Models\ChoreAssignment;
use App\Models\ChoreCompletion;
use App\Models\FamilyMember;
use App\Models\User;

test('toggle creates completion record', function () {
    $user = createAdminUser();
    $member = FamilyMember::where('user_id', $user->id)->first();
    $chore = Chore::factory()->create(['user_id' => $user->id, 'points' => 10]);
    $assignment = ChoreAssignment::factory()->create([
        'chore_id' => $chore->id,
        'family_member_id' => $member->id,
        'day_of_week' => 1,
    ]);

    $this->actingAs($user)
        ->postJson('/chore-completions/toggle', [
            'chore_assignment_id' => $assignment->id,
            'date' => now()->toDateString(),
        ])
        ->assertOk()
        ->assertJson(['completed' => true, 'points_earned' => 10]);

    $this->assertDatabaseHas('chore_completions', [
        'chore_assignment_id' => $assignment->id,
        'family_member_id' => $member->id,
        'points_earned' => 10,
    ]);
});

test('toggle existing completion removes it', function () {
    $user = createAdminUser();
    $member = FamilyMember::where('user_id', $user->id)->first();
    $chore = Chore::factory()->create(['user_id' => $user->id, 'points' => 10]);
    $assignment = ChoreAssignment::factory()->create([
        'chore_id' => $chore->id,
        'family_member_id' => $member->id,
        'day_of_week' => 1,
    ]);

    ChoreCompletion::create([
        'chore_assignment_id' => $assignment->id,
        'family_member_id' => $member->id,
        'completed_date' => now()->toDateString(),
        'points_earned' => 10,
    ]);

    $this->actingAs($user)
        ->postJson('/chore-completions/toggle', [
            'chore_assignment_id' => $assignment->id,
            'date' => now()->toDateString(),
        ])
        ->assertOk()
        ->assertJson(['completed' => false, 'points_earned' => 0]);

    $this->assertDatabaseMissing('chore_completions', [
        'chore_assignment_id' => $assignment->id,
        'completed_date' => now()->toDateString(),
    ]);
});

test('cannot toggle another family\'s assignment', function () {
    $user = createAdminUser();
    $otherUser = User::factory()->create();
    $chore = Chore::factory()->create(['user_id' => $otherUser->id, 'points' => 5]);
    $otherMember = FamilyMember::factory()->create(['user_id' => $otherUser->id]);
    $assignment = ChoreAssignment::factory()->create([
        'chore_id' => $chore->id,
        'family_member_id' => $otherMember->id,
        'day_of_week' => 1,
    ]);

    $this->actingAs($user)
        ->postJson('/chore-completions/toggle', [
            'chore_assignment_id' => $assignment->id,
            'date' => now()->toDateString(),
        ])
        ->assertForbidden();
});

test('toggle requires valid chore_assignment_id', function () {
    $user = createAdminUser();

    $this->actingAs($user)
        ->postJson('/chore-completions/toggle', [
            'chore_assignment_id' => 999999,
            'date' => now()->toDateString(),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('chore_assignment_id');
});

test('toggle requires valid date', function () {
    $user = createAdminUser();

    $this->actingAs($user)
        ->postJson('/chore-completions/toggle', [
            'chore_assignment_id' => 1,
            'date' => 'not-a-date',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('date');
});

test('toggle requires all fields', function () {
    $user = createAdminUser();

    $this->actingAs($user)
        ->postJson('/chore-completions/toggle', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['chore_assignment_id', 'date']);
});

function kidWithAssignment(?bool $sibling = false): array
{
    $parent = createAdminUser();
    $kidUser = User::factory()->create();
    $kidUser->assignRole('child');
    $kid = FamilyMember::factory()->create(['user_id' => $parent->id, 'linked_user_id' => $kidUser->id]);
    $other = $sibling ? FamilyMember::factory()->create(['user_id' => $parent->id, 'linked_user_id' => null]) : $kid;
    $chore = Chore::factory()->create(['user_id' => $parent->id, 'points' => 50]);
    $assignment = ChoreAssignment::factory()->create(['chore_id' => $chore->id, 'family_member_id' => $other->id, 'day_of_week' => 1]);

    return [$parent, $kidUser, $assignment];
}

test('kids can complete their own chore today', function () {
    [, $kid, $a] = kidWithAssignment();

    $this->actingAs($kid)->postJson('/chore-completions/toggle', ['chore_assignment_id' => $a->id, 'date' => now()->toDateString()])
        ->assertOk()->assertJson(['completed' => true]);
});

test('kids cannot complete a siblings chore', function () {
    [, $kid, $a] = kidWithAssignment(sibling: true);

    $this->actingAs($kid)->postJson('/chore-completions/toggle', ['chore_assignment_id' => $a->id, 'date' => now()->toDateString()])
        ->assertForbidden();
    expect(ChoreCompletion::count())->toBe(0);
});

test('completions outside the allowed window are rejected', function () {
    [$parent, $kid, $a] = kidWithAssignment();

    foreach ([$kid, $parent] as $who) {
        foreach ([now()->addDays(5), now()->subDays(30)] as $date) {
            $this->actingAs($who)->postJson('/chore-completions/toggle', ['chore_assignment_id' => $a->id, 'date' => $date->toDateString()])
                ->assertStatus(422);
        }
    }
    expect(ChoreCompletion::count())->toBe(0);
});

test('parents can backfill a recent day for any member', function () {
    [$parent, , $a] = kidWithAssignment(sibling: true);

    $this->actingAs($parent)->postJson('/chore-completions/toggle', ['chore_assignment_id' => $a->id, 'date' => now()->subDays(3)->toDateString()])
        ->assertOk()->assertJson(['completed' => true]);
});

function approvalSetup(): array
{
    [$parent, $kid, $a] = kidWithAssignment();
    $a->chore->update(['requires_approval' => true]);

    return [$parent, $kid, $a];
}

test('a kid completing an approval chore is pending and earns nothing yet', function () {
    \Illuminate\Support\Facades\Notification::fake();
    [$parent, $kid, $a] = approvalSetup();
    $parent->updatePushSubscription('https://push.example.com/p', 'k', 'a');

    $this->actingAs($kid)->postJson('/chore-completions/toggle', ['chore_assignment_id' => $a->id, 'date' => now()->toDateString()])
        ->assertOk()->assertJson(['completed' => true, 'status' => 'pending', 'points_earned' => 0]);

    expect(ChoreCompletion::count())->toBe(0) // hidden from every points query
        ->and(ChoreCompletion::withoutGlobalScope('approved')->count())->toBe(1);
    \Illuminate\Support\Facades\Notification::assertSentTo($parent, \App\Notifications\PushAlert::class);
});

test('pending completions do not count for the scoreboard or reward balance', function () {
    [$parent, $kid, $a] = approvalSetup();
    $memberId = $a->family_member_id;
    $payload = ['chore_assignment_id' => $a->id, 'date' => now()->toDateString()];

    $this->actingAs($kid)->postJson('/chore-completions/toggle', $payload);
    expect(app(\App\Services\RewardService::class)->balances($parent->id)[$memberId] ?? 0)->toBe(0);

    $id = ChoreCompletion::withoutGlobalScope('approved')->first()->id;
    $this->actingAs($parent)->patchJson("/chore-completions/{$id}", ['status' => 'approved'])->assertOk();
    expect(app(\App\Services\RewardService::class)->balances($parent->id)[$memberId])->toBe(50);
});

test('a parent can approve and the points then count', function () {
    [$parent, $kid, $a] = approvalSetup();
    $this->actingAs($kid)->postJson('/chore-completions/toggle', ['chore_assignment_id' => $a->id, 'date' => now()->toDateString()]);
    $pending = ChoreCompletion::withoutGlobalScope('approved')->first();

    $this->actingAs($kid)->patchJson("/chore-completions/{$pending->id}", ['status' => 'approved'])->assertForbidden();
    $this->actingAs($parent)->patchJson("/chore-completions/{$pending->id}", ['status' => 'approved'])->assertOk();
    $this->actingAs($parent)->patchJson("/chore-completions/{$pending->id}", ['status' => 'approved'])->assertStatus(422);

    expect(ChoreCompletion::count())->toBe(1)
        ->and(ChoreCompletion::first()->approved_by)->toBe($parent->id);
});

test('rejecting removes the pending completion', function () {
    [$parent, $kid, $a] = approvalSetup();
    $this->actingAs($kid)->postJson('/chore-completions/toggle', ['chore_assignment_id' => $a->id, 'date' => now()->toDateString()]);
    $pending = ChoreCompletion::withoutGlobalScope('approved')->first();

    $this->actingAs($parent)->patchJson("/chore-completions/{$pending->id}", ['status' => 'rejected'])->assertOk();

    expect(ChoreCompletion::withoutGlobalScope('approved')->count())->toBe(0);
});

test('a kid can withdraw a pending chore but not undo an approved one', function () {
    [$parent, $kid, $a] = approvalSetup();
    $payload = ['chore_assignment_id' => $a->id, 'date' => now()->toDateString()];

    $this->actingAs($kid)->postJson('/chore-completions/toggle', $payload);
    $this->actingAs($kid)->postJson('/chore-completions/toggle', $payload)->assertOk()->assertJson(['completed' => false]);

    $this->actingAs($kid)->postJson('/chore-completions/toggle', $payload);
    $id = ChoreCompletion::withoutGlobalScope('approved')->first()->id;
    $this->actingAs($parent)->patchJson("/chore-completions/{$id}", ['status' => 'approved']);

    $this->actingAs($kid)->postJson('/chore-completions/toggle', $payload)->assertForbidden();
});

test('parents completing an approval chore are auto-approved, and ordinary chores skip approval', function () {
    [$parent, $kid, $a] = approvalSetup();
    $this->actingAs($parent)->postJson('/chore-completions/toggle', ['chore_assignment_id' => $a->id, 'date' => now()->toDateString()])
        ->assertOk()->assertJson(['status' => 'approved', 'points_earned' => 50]);

    [, $kid2, $a2] = kidWithAssignment();
    $this->actingAs($kid2)->postJson('/chore-completions/toggle', ['chore_assignment_id' => $a2->id, 'date' => now()->toDateString()])
        ->assertOk()->assertJson(['status' => 'approved', 'points_earned' => 50]);
});

test('the approval queue is only shown to parents and is scoped to the family', function () {
    [$parent, $kid, $a] = approvalSetup();
    $this->actingAs($kid)->postJson('/chore-completions/toggle', ['chore_assignment_id' => $a->id, 'date' => now()->toDateString()]);

    $this->actingAs($parent)->get('/chores')->assertInertia(fn ($p) => $p->has('pendingApprovals', 1));
    $this->actingAs($kid)->get('/chores')->assertInertia(fn ($p) => $p->has('pendingApprovals', 0));

    $stranger = createAdminUser();
    $this->actingAs($stranger)->get('/chores')->assertInertia(fn ($p) => $p->has('pendingApprovals', 0));
    $id = ChoreCompletion::withoutGlobalScope('approved')->first()->id;
    $this->actingAs($stranger)->patchJson("/chore-completions/{$id}", ['status' => 'approved'])->assertForbidden();
});
