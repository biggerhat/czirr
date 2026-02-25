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
