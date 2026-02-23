<?php

use App\Models\Chore;
use App\Models\ChoreAssignment;
use App\Models\FamilyMember;
use App\Models\User;

test('admin can toggle assignment on', function () {
    $user = createAdminUser();
    $chore = Chore::factory()->create(['user_id' => $user->id]);
    $member = FamilyMember::where('user_id', $user->id)->first();

    $this->actingAs($user)
        ->postJson('/chore-assignments/toggle', [
            'chore_id' => $chore->id,
            'family_member_id' => $member->id,
            'day_of_week' => 1,
        ])
        ->assertCreated();

    $this->assertDatabaseHas('chore_assignments', [
        'chore_id' => $chore->id,
        'family_member_id' => $member->id,
        'day_of_week' => 1,
    ]);
});

test('admin can toggle assignment off', function () {
    $user = createAdminUser();
    $chore = Chore::factory()->create(['user_id' => $user->id]);
    $member = FamilyMember::where('user_id', $user->id)->first();

    ChoreAssignment::factory()->create([
        'chore_id' => $chore->id,
        'family_member_id' => $member->id,
        'day_of_week' => 1,
    ]);

    $this->actingAs($user)
        ->postJson('/chore-assignments/toggle', [
            'chore_id' => $chore->id,
            'family_member_id' => $member->id,
            'day_of_week' => 1,
        ])
        ->assertOk()
        ->assertJsonFragment(['removed' => true]);

    $this->assertDatabaseMissing('chore_assignments', [
        'chore_id' => $chore->id,
        'family_member_id' => $member->id,
        'day_of_week' => 1,
    ]);
});

test('cannot toggle assignment for another user\'s chore', function () {
    $user = createAdminUser();
    $other = User::factory()->create();
    $chore = Chore::factory()->create(['user_id' => $other->id]);
    $member = FamilyMember::where('user_id', $user->id)->first();

    $this->actingAs($user)
        ->postJson('/chore-assignments/toggle', [
            'chore_id' => $chore->id,
            'family_member_id' => $member->id,
            'day_of_week' => 1,
        ])
        ->assertForbidden();
});

test('toggle requires valid fields', function () {
    $user = createAdminUser();

    $this->actingAs($user)
        ->postJson('/chore-assignments/toggle', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['chore_id', 'family_member_id', 'day_of_week']);
});

test('child role cannot toggle assignments', function () {
    $user = createUserWithRole('child');

    $this->actingAs($user)
        ->postJson('/chore-assignments/toggle', [
            'chore_id' => 1,
            'family_member_id' => 1,
            'day_of_week' => 1,
        ])
        ->assertForbidden();
});
