<?php

use App\Models\Contact;
use App\Models\Event;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $this->get(route('contacts.index'))->assertRedirect(route('login'));
});

test('authenticated user can view contacts', function () {
    $user = createAdminUser();

    $this->actingAs($user)
        ->get(route('contacts.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('contacts/Index'));
});

test('contacts can be searched', function () {
    $user = createAdminUser();
    Contact::factory()->create(['user_id' => $user->id, 'first_name' => 'Alice', 'last_name' => 'Smith']);
    Contact::factory()->create(['user_id' => $user->id, 'first_name' => 'Bob', 'last_name' => 'Jones']);

    $this->actingAs($user)
        ->get(route('contacts.index', ['search' => 'Alice']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('contacts', 1));
});

test('admin can create a contact', function () {
    $user = createAdminUser();

    $this->actingAs($user)
        ->postJson('/contacts', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '555-1234',
        ])
        ->assertCreated()
        ->assertJsonFragment(['first_name' => 'John']);

    $this->assertDatabaseHas('contacts', ['first_name' => 'John', 'user_id' => $user->id]);
});

test('creating contact with date_of_birth creates birthday event', function () {
    $user = createAdminUser();

    $this->actingAs($user)
        ->postJson('/contacts', [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'date_of_birth' => '1990-06-15',
        ])
        ->assertCreated();

    $contact = Contact::where('first_name', 'Jane')->first();
    expect($contact->birthdayEvent)->not->toBeNull();
    expect($contact->birthdayEvent->title)->toBe("Jane Doe's Birthday");
    expect($contact->birthdayEvent->rrule)->toBe('FREQ=YEARLY');
});

test('updating contact syncs birthday event', function () {
    $user = createAdminUser();
    $contact = Contact::factory()->create([
        'user_id' => $user->id,
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'date_of_birth' => '1990-06-15',
    ]);
    // Manually create the birthday event like the controller does
    $this->actingAs($user)->putJson("/contacts/{$contact->id}", [
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'date_of_birth' => '1990-07-20',
    ]);

    $contact->refresh();
    expect($contact->birthdayEvent)->not->toBeNull();
});

test('removing date_of_birth deletes birthday event', function () {
    $user = createAdminUser();

    // Create with birthday
    $this->actingAs($user)->postJson('/contacts', [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'date_of_birth' => '1990-06-15',
    ])->assertCreated();

    $contact = Contact::where('first_name', 'Jane')->first();
    $eventId = $contact->birthdayEvent->id;

    // Update without birthday
    $this->actingAs($user)->putJson("/contacts/{$contact->id}", [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'date_of_birth' => null,
    ])->assertOk();

    $this->assertDatabaseMissing('events', ['id' => $eventId]);
});

test('user cannot update another user\'s contact', function () {
    $user = createAdminUser();
    $other = User::factory()->create();
    $contact = Contact::factory()->create(['user_id' => $other->id]);

    $this->actingAs($user)
        ->putJson("/contacts/{$contact->id}", ['first_name' => 'Hacked'])
        ->assertForbidden();
});

test('deleting contact also deletes birthday event', function () {
    $user = createAdminUser();

    $this->actingAs($user)->postJson('/contacts', [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'date_of_birth' => '1990-06-15',
    ])->assertCreated();

    $contact = Contact::where('first_name', 'Jane')->first();
    $eventId = $contact->birthdayEvent->id;

    $this->actingAs($user)->deleteJson("/contacts/{$contact->id}")->assertNoContent();

    $this->assertDatabaseMissing('contacts', ['id' => $contact->id]);
    $this->assertDatabaseMissing('events', ['id' => $eventId]);
});

test('creating contact requires first_name', function () {
    $user = createAdminUser();

    $this->actingAs($user)
        ->postJson('/contacts', ['last_name' => 'Doe'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('first_name');
});

test('child role cannot create contacts', function () {
    $user = createUserWithRole('child');

    $this->actingAs($user)
        ->postJson('/contacts', ['first_name' => 'Test'])
        ->assertForbidden();
});
