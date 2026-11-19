<?php

use App\Models\FamilyMember;
use App\Models\SavingsEntry;

test('managers add and pay out money and the balance adds up', function () {
    $admin = createAdminUser();
    $kid = FamilyMember::factory()->create(['user_id' => $admin->id, 'name' => 'Sam']);

    $this->actingAs($admin)->postJson('/savings', ['family_member_id' => $kid->id, 'amount' => 20, 'note' => 'Allowance'])->assertCreated();
    $this->actingAs($admin)->postJson('/savings', ['family_member_id' => $kid->id, 'amount' => -7.5])->assertCreated();

    $this->actingAs($admin)->get('/savings')->assertInertia(fn ($p) => $p
        ->component('savings/Index')
        ->where('can.manage', true)
        ->where('accounts', fn ($accounts) => collect($accounts)->firstWhere('id', $kid->id)['balance'] === 12.5));
});

test('zero amounts and other families\' members are rejected', function () {
    $admin = createAdminUser();
    $other = createAdminUser();
    $theirKid = FamilyMember::factory()->create(['user_id' => $other->id]);
    $mine = FamilyMember::factory()->create(['user_id' => $admin->id]);

    $this->actingAs($admin)->postJson('/savings', ['family_member_id' => $mine->id, 'amount' => 0])->assertUnprocessable();
    $this->actingAs($admin)->postJson('/savings', ['family_member_id' => $theirKid->id, 'amount' => 5])->assertUnprocessable();
});

test('a child only sees their own account and cannot add money', function () {
    $child = createUserWithRole('child');
    $ownerId = $child->familyOwnerId();
    $own = $child->linkedFamilyMember();
    $sibling = FamilyMember::factory()->create(['user_id' => $ownerId, 'name' => 'Sibling']);
    SavingsEntry::unguarded(fn () => SavingsEntry::create(['user_id' => $ownerId, 'family_member_id' => $own->id, 'amount' => 5, 'entry_date' => now()]));
    SavingsEntry::unguarded(fn () => SavingsEntry::create(['user_id' => $ownerId, 'family_member_id' => $sibling->id, 'amount' => 99, 'entry_date' => now()]));

    $this->actingAs($child)->get('/savings')->assertInertia(fn ($p) => $p
        ->where('can.manage', false)
        ->has('accounts', 1)
        ->where('accounts.0.balance', 5));

    $this->actingAs($child)->postJson('/savings', ['family_member_id' => $own->id, 'amount' => 50])->assertForbidden();
});

test('entries can only be deleted within your own family', function () {
    $admin = createAdminUser();
    $other = createAdminUser();
    $theirKid = FamilyMember::factory()->create(['user_id' => $other->id]);
    $entry = SavingsEntry::unguarded(fn () => SavingsEntry::create(['user_id' => $other->id, 'family_member_id' => $theirKid->id, 'amount' => 5, 'entry_date' => now()]));

    $this->actingAs($admin)->deleteJson("/savings/{$entry->id}")->assertForbidden();
    $this->actingAs($other)->deleteJson("/savings/{$entry->id}")->assertNoContent();
});
