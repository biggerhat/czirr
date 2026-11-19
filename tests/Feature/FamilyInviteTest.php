<?php

use App\Models\FamilyInvite;
use App\Models\FamilyMember;
use App\Models\User;

function makeInvite(User $owner, array $payload): string
{
    $res = test()->actingAs($owner)->postJson('/family/invites', $payload)->assertCreated();

    return basename($res->json('url'));
}

test('a parent can invite an existing member and the invitee joins as that member', function () {
    $owner = createAdminUser();
    $kid = FamilyMember::factory()->create(['user_id' => $owner->id, 'name' => 'Maya', 'role' => 'child', 'linked_user_id' => null]);
    $token = makeInvite($owner, ['family_member_id' => $kid->id, 'role' => 'parent']);

    $newUser = User::factory()->create();
    $this->actingAs($newUser)->post("/invite/{$token}/accept")->assertRedirect('/dashboard');

    expect($kid->fresh()->linked_user_id)->toBe($newUser->id)
        ->and($newUser->fresh()->hasRole('child'))->toBeTrue() // role comes from the member, not the request
        ->and($newUser->fresh()->familyOwnerId())->toBe($owner->id);
});

test('inviting someone new creates their family member on accept', function () {
    $owner = createAdminUser();
    $token = makeInvite($owner, ['name' => 'Grandma', 'role' => 'parent']);

    $user = User::factory()->create();
    $this->actingAs($user)->post("/invite/{$token}/accept")->assertRedirect('/dashboard');

    $member = FamilyMember::where('linked_user_id', $user->id)->first();
    expect($member->user_id)->toBe($owner->id)
        ->and($member->name)->toBe('Grandma')
        ->and($user->fresh()->hasRole('parent'))->toBeTrue();
});

test('invites are single use and tokens are not stored in plain text', function () {
    $owner = createAdminUser();
    $token = makeInvite($owner, ['name' => 'Once', 'role' => 'child']);

    expect(FamilyInvite::where('token_hash', $token)->exists())->toBeFalse()
        ->and(FamilyInvite::findByToken($token))->not->toBeNull();

    $this->actingAs(User::factory()->create())->post("/invite/{$token}/accept");
    $second = User::factory()->create();
    $this->actingAs($second)->post("/invite/{$token}/accept")->assertSessionHas('error');

    expect(FamilyMember::where('linked_user_id', $second->id)->exists())->toBeFalse();
});

test('expired invites cannot be accepted', function () {
    $owner = createAdminUser();
    $token = makeInvite($owner, ['name' => 'Late', 'role' => 'child']);
    FamilyInvite::query()->update(['expires_at' => now()->subMinute()]);

    $user = User::factory()->create();
    $this->actingAs($user)->post("/invite/{$token}/accept")->assertSessionHas('error');

    expect(FamilyMember::where('linked_user_id', $user->id)->exists())->toBeFalse();
});

test('someone who already belongs to a family cannot be moved by an invite', function () {
    $owner = createAdminUser();
    $token = makeInvite($owner, ['name' => 'X', 'role' => 'child']);
    $other = createAdminUser();

    $this->actingAs($other)->post("/invite/{$token}/accept")->assertSessionHas('error');

    expect($other->fresh()->familyOwnerId())->toBe($other->id);
});

test('the landing page works for guests and remembers where to return', function () {
    $owner = createAdminUser();
    $token = makeInvite($owner, ['name' => 'Guest', 'role' => 'child']);

    auth()->logout(); // makeInvite() acted as the owner

    $this->get("/invite/{$token}")
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('invite/Show')->where('state', 'guest')->where('family', $owner->name));
    expect(session('url.intended'))->toContain("/invite/{$token}");

    $this->get('/invite/'.str_repeat('a', 48))->assertInertia(fn ($p) => $p->where('state', 'invalid'));
});

test('only users who can add family members can create invites, and only for their own family', function () {
    $owner = createAdminUser();
    $child = User::factory()->create();
    $child->assignRole('child');
    FamilyMember::factory()->create(['user_id' => $owner->id, 'role' => 'child', 'linked_user_id' => $child->id]);
    $theirMember = FamilyMember::factory()->create(['user_id' => createAdminUser()->id, 'linked_user_id' => null]);

    $this->actingAs($child)->postJson('/family/invites', ['name' => 'x', 'role' => 'parent'])->assertForbidden();
    $this->actingAs($owner)->postJson('/family/invites', ['family_member_id' => $theirMember->id, 'role' => 'child'])->assertUnprocessable();
});

test('with registration closed, strangers cannot sign up but invitees can', function () {
    config(['app.registration_open' => false]);
    $owner = createAdminUser();
    $data = ['name' => 'New', 'email' => 'new@example.com', 'password' => 'a-Strong-pass-123!', 'password_confirmation' => 'a-Strong-pass-123!'];

    $this->post('/register', $data)->assertSessionHasErrors('email');
    expect(User::where('email', 'new@example.com')->exists())->toBeFalse();

    $token = makeInvite($owner, ['name' => 'New', 'role' => 'child']);
    auth()->logout();
    $this->get("/invite/{$token}");
    $this->post('/register', $data)->assertSessionHasNoErrors();
    expect(User::where('email', 'new@example.com')->exists())->toBeTrue();
});

test('the first account can always register even when closed', function () {
    config(['app.registration_open' => false]);

    $this->post('/register', ['name' => 'First', 'email' => 'first@example.com', 'password' => 'a-Strong-pass-123!', 'password_confirmation' => 'a-Strong-pass-123!'])
        ->assertSessionHasNoErrors();
    expect(User::first()->hasRole('admin'))->toBeTrue();
});
