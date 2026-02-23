<?php

use App\Models\Event;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $this->getJson(route('events.index'))->assertUnauthorized();
});

test('child role cannot create events', function () {
    $user = createUserWithRole('child');

    $this->actingAs($user)
        ->postJson(route('events.store'), [
            'title' => 'Test',
            'start_date' => '2026-03-01',
            'start_time' => '10:00',
            'end_date' => '2026-03-01',
            'end_time' => '11:00',
            'timezone' => 'UTC',
        ])
        ->assertForbidden();
});

test('admin can create an event', function () {
    $user = createAdminUser();

    $this->actingAs($user)
        ->postJson(route('events.store'), [
            'title' => 'Team Meeting',
            'start_date' => '2026-03-01',
            'start_time' => '10:00',
            'end_date' => '2026-03-01',
            'end_time' => '11:00',
            'timezone' => 'UTC',
        ])
        ->assertCreated()
        ->assertJsonFragment(['title' => 'Team Meeting']);

    $this->assertDatabaseHas('events', ['title' => 'Team Meeting', 'user_id' => $user->id]);
});

test('admin can create an all-day event', function () {
    $user = createAdminUser();

    $this->actingAs($user)
        ->postJson(route('events.store'), [
            'title' => 'Vacation',
            'start_date' => '2026-03-15',
            'end_date' => '2026-03-20',
            'is_all_day' => true,
            'timezone' => 'UTC',
        ])
        ->assertCreated()
        ->assertJsonFragment(['title' => 'Vacation', 'is_all_day' => true]);
});

test('admin can update own event', function () {
    $user = createAdminUser();
    $event = Event::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->putJson(route('events.update', $event), [
            'title' => 'Updated Title',
            'start_date' => '2026-03-01',
            'start_time' => '10:00',
            'end_date' => '2026-03-01',
            'end_time' => '12:00',
            'timezone' => 'UTC',
        ])
        ->assertOk()
        ->assertJsonFragment(['title' => 'Updated Title']);
});

test('user cannot update another user\'s event', function () {
    $user = createAdminUser();
    $other = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $other->id]);

    $this->actingAs($user)
        ->putJson(route('events.update', $event), [
            'title' => 'Hacked',
            'start_date' => '2026-03-01',
            'start_time' => '10:00',
            'end_date' => '2026-03-01',
            'end_time' => '11:00',
            'timezone' => 'UTC',
        ])
        ->assertForbidden();
});

test('admin can delete own event', function () {
    $user = createAdminUser();
    $event = Event::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->deleteJson(route('events.destroy', $event))
        ->assertNoContent();

    $this->assertDatabaseMissing('events', ['id' => $event->id]);
});

test('user cannot delete another user\'s event', function () {
    $user = createAdminUser();
    $other = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $other->id]);

    $this->actingAs($user)
        ->deleteJson(route('events.destroy', $event))
        ->assertForbidden();
});

test('creating event requires title', function () {
    $user = createAdminUser();

    $this->actingAs($user)
        ->postJson(route('events.store'), [
            'start_date' => '2026-03-01',
            'start_time' => '10:00',
            'end_date' => '2026-03-01',
            'end_time' => '11:00',
            'timezone' => 'UTC',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('title');
});
