<?php

use App\Models\Chore;
use App\Models\FamilyMember;
use App\Models\Recipe;
use App\Models\User;

test('the family owner can download an export of their own data only', function () {
    $owner = createAdminUser();
    $other = createAdminUser();
    Chore::factory()->create(['user_id' => $owner->id, 'name' => 'Mine-Chore']);
    Chore::factory()->create(['user_id' => $other->id, 'name' => 'Theirs-Chore']);
    Recipe::factory()->create(['user_id' => $owner->id, 'name' => 'Mine-Recipe']);

    $res = $this->actingAs($owner)->get('/settings/export')->assertOk();
    $body = $res->streamedContent();

    expect($body)->toContain('Mine-Chore')->toContain('Mine-Recipe')->not->toContain('Theirs-Chore')
        ->and($res->headers->get('content-disposition'))->toContain('family-export-');
});

test('the export never contains credentials', function () {
    $owner = createAdminUser();
    $body = $this->actingAs($owner)->get('/settings/export')->streamedContent();

    expect($body)->not->toContain('password')->not->toContain('remember_token')->not->toContain($owner->email);
});

test('only the family owner can export', function () {
    $owner = createAdminUser();
    $child = User::factory()->create();
    $child->assignRole('child');
    FamilyMember::factory()->create(['user_id' => $owner->id, 'role' => 'child', 'linked_user_id' => $child->id]);

    $this->actingAs($child)->get('/settings/export')->assertForbidden();
    $this->get('/logout');
});

test('an owner cannot delete their account while other members still depend on it', function () {
    $owner = createAdminUser();
    $child = User::factory()->create();
    FamilyMember::factory()->create(['user_id' => $owner->id, 'role' => 'child', 'linked_user_id' => $child->id]);
    Chore::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($owner)->delete('/settings/profile', ['password' => 'password'])
        ->assertSessionHasErrors('password');

    expect(User::find($owner->id))->not->toBeNull()->and(Chore::count())->toBe(1);
});

test('a solo owner and a family member can still delete their own account', function () {
    $solo = createAdminUser();
    $this->actingAs($solo)->delete('/settings/profile', ['password' => 'password'])->assertRedirect();
    expect(User::find($solo->id))->toBeNull();

    $owner = createAdminUser();
    $kid = User::factory()->create();
    FamilyMember::factory()->create(['user_id' => $owner->id, 'role' => 'child', 'linked_user_id' => $kid->id]);
    $this->actingAs($kid)->delete('/settings/profile', ['password' => 'password'])->assertRedirect();
    expect(User::find($kid->id))->toBeNull()->and(User::find($owner->id))->not->toBeNull();
});
