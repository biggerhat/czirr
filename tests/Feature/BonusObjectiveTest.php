<?php

use App\Models\BonusObjective;
use App\Models\FamilyMember;
use App\Models\User;

test('admin can create bonus objective', function () {
    $user = createAdminUser();

    $this->actingAs($user)
        ->postJson('/bonus-objectives', [
            'name' => 'Clean the garage',
            'description' => 'Sweep and organize',
            'points' => 50,
        ])
        ->assertCreated()
        ->assertJsonFragment(['name' => 'Clean the garage']);

    $this->assertDatabaseHas('bonus_objectives', [
        'name' => 'Clean the garage',
        'user_id' => $user->id,
        'points' => 50,
    ]);
});

test('validates required fields on create', function () {
    $user = createAdminUser();

    $this->actingAs($user)
        ->postJson('/bonus-objectives', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'points']);
});

test('admin can update unclaimed objective', function () {
    $user = createAdminUser();
    $objective = BonusObjective::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->putJson("/bonus-objectives/{$objective->id}", [
            'name' => 'Updated objective',
            'points' => 75,
        ])
        ->assertOk()
        ->assertJsonFragment(['name' => 'Updated objective']);
});

test('cannot update claimed objective', function () {
    $user = createAdminUser();
    $member = FamilyMember::where('user_id', $user->id)->first();
    $objective = BonusObjective::factory()->claimed($member)->create([
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->putJson("/bonus-objectives/{$objective->id}", [
            'name' => 'Try to update',
            'points' => 99,
        ])
        ->assertStatus(422);
});

test('admin can delete objective', function () {
    $user = createAdminUser();
    $objective = BonusObjective::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->deleteJson("/bonus-objectives/{$objective->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('bonus_objectives', ['id' => $objective->id]);
});

test('cannot modify another family\'s objective', function () {
    $user = createAdminUser();
    $otherUser = User::factory()->create();
    $objective = BonusObjective::factory()->create(['user_id' => $otherUser->id]);

    $this->actingAs($user)
        ->putJson("/bonus-objectives/{$objective->id}", [
            'name' => 'Hacked',
            'points' => 999,
        ])
        ->assertForbidden();
});

test('can claim objective with valid family member', function () {
    $user = createAdminUser();
    $member = FamilyMember::where('user_id', $user->id)->first();
    $objective = BonusObjective::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->postJson("/bonus-objectives/{$objective->id}/claim", [
            'family_member_id' => $member->id,
        ])
        ->assertOk()
        ->assertJsonFragment(['claimed_by' => $member->id]);

    $this->assertDatabaseHas('bonus_objectives', [
        'id' => $objective->id,
        'claimed_by' => $member->id,
    ]);
});

test('cannot claim already claimed objective', function () {
    $user = createAdminUser();
    $member = FamilyMember::where('user_id', $user->id)->first();
    $objective = BonusObjective::factory()->claimed($member)->create([
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->postJson("/bonus-objectives/{$objective->id}/claim", [
            'family_member_id' => $member->id,
        ])
        ->assertStatus(422);
});

test('cannot claim with member from another family', function () {
    $user = createAdminUser();
    $otherUser = User::factory()->create();
    $otherMember = FamilyMember::factory()->create(['user_id' => $otherUser->id]);
    $objective = BonusObjective::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->postJson("/bonus-objectives/{$objective->id}/claim", [
            'family_member_id' => $otherMember->id,
        ])
        ->assertNotFound();
});

test('child role cannot create bonus objectives', function () {
    $user = createUserWithRole('child');

    $this->actingAs($user)
        ->postJson('/bonus-objectives', [
            'name' => 'Test',
            'points' => 10,
        ])
        ->assertForbidden();
});
