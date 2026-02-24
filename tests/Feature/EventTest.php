<?php

use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

// ─── Cache Invalidation ──────────────────────────────────────────────

test('index response is served from cache on repeat requests', function () {
    $user = createAdminUser();

    $event = Event::factory()->create([
        'user_id' => $user->id,
        'starts_at' => '2026-03-10 10:00:00',
        'ends_at' => '2026-03-10 11:00:00',
    ]);

    $params = ['start' => '2026-03-01', 'end' => '2026-03-31', 'timezone' => 'UTC'];

    // First request — cache miss, populates cache
    $first = $this->actingAs($user)
        ->getJson(route('events.index', $params))
        ->assertOk();

    // Verify the version key and a calendar cache entry now exist
    $version = Cache::get("calendar:v:{$user->id}", 0);
    $cacheKey = "calendar:{$user->id}:{$version}:".md5(
        \Carbon\Carbon::parse('2026-03-01', 'UTC')->utc()->timestamp
        .':'.
        \Carbon\Carbon::parse('2026-03-31', 'UTC')->utc()->timestamp
    );

    expect(Cache::has($cacheKey))->toBeTrue();

    // Second request — should return identical data (from cache)
    $second = $this->actingAs($user)
        ->getJson(route('events.index', $params))
        ->assertOk();

    expect($second->json())->toEqual($first->json());
});

test('creating an event busts the calendar cache', function () {
    $user = createAdminUser();

    Event::factory()->create([
        'user_id' => $user->id,
        'starts_at' => '2026-03-10 10:00:00',
        'ends_at' => '2026-03-10 11:00:00',
    ]);

    $params = ['start' => '2026-03-01', 'end' => '2026-03-31', 'timezone' => 'UTC'];

    // Populate cache
    $this->actingAs($user)
        ->getJson(route('events.index', $params))
        ->assertOk()
        ->assertJsonCount(1);

    $versionBefore = Cache::get("calendar:v:{$user->id}", 0);

    // Create a new event via the API
    $this->actingAs($user)
        ->postJson(route('events.store'), [
            'title' => 'New Event',
            'start_date' => '2026-03-15',
            'start_time' => '14:00',
            'end_date' => '2026-03-15',
            'end_time' => '15:00',
            'timezone' => 'UTC',
        ])
        ->assertCreated();

    // Version counter should have incremented
    $versionAfter = Cache::get("calendar:v:{$user->id}", 0);
    expect($versionAfter)->toBeGreaterThan($versionBefore);

    // Next index request should include the new event
    $this->actingAs($user)
        ->getJson(route('events.index', $params))
        ->assertOk()
        ->assertJsonCount(2);
});

test('updating an event busts the calendar cache', function () {
    $user = createAdminUser();

    $event = Event::factory()->create([
        'user_id' => $user->id,
        'title' => 'Original Title',
        'starts_at' => '2026-03-10 10:00:00',
        'ends_at' => '2026-03-10 11:00:00',
    ]);

    $params = ['start' => '2026-03-01', 'end' => '2026-03-31', 'timezone' => 'UTC'];

    // Populate cache
    $this->actingAs($user)
        ->getJson(route('events.index', $params))
        ->assertOk()
        ->assertJsonFragment(['title' => 'Original Title']);

    $versionBefore = Cache::get("calendar:v:{$user->id}", 0);

    // Update the event
    $this->actingAs($user)
        ->putJson(route('events.update', $event), [
            'title' => 'Updated Title',
            'start_date' => '2026-03-10',
            'start_time' => '10:00',
            'end_date' => '2026-03-10',
            'end_time' => '11:00',
            'timezone' => 'UTC',
        ])
        ->assertOk();

    // Version counter should have incremented
    $versionAfter = Cache::get("calendar:v:{$user->id}", 0);
    expect($versionAfter)->toBeGreaterThan($versionBefore);

    // Next index request should reflect the update
    $this->actingAs($user)
        ->getJson(route('events.index', $params))
        ->assertOk()
        ->assertJsonFragment(['title' => 'Updated Title'])
        ->assertJsonMissing(['title' => 'Original Title']);
});

test('deleting an event busts the calendar cache', function () {
    $user = createAdminUser();

    $event = Event::factory()->create([
        'user_id' => $user->id,
        'starts_at' => '2026-03-10 10:00:00',
        'ends_at' => '2026-03-10 11:00:00',
    ]);

    $params = ['start' => '2026-03-01', 'end' => '2026-03-31', 'timezone' => 'UTC'];

    // Populate cache
    $this->actingAs($user)
        ->getJson(route('events.index', $params))
        ->assertOk()
        ->assertJsonCount(1);

    $versionBefore = Cache::get("calendar:v:{$user->id}", 0);

    // Delete the event
    $this->actingAs($user)
        ->deleteJson(route('events.destroy', $event))
        ->assertNoContent();

    // Version counter should have incremented
    $versionAfter = Cache::get("calendar:v:{$user->id}", 0);
    expect($versionAfter)->toBeGreaterThan($versionBefore);

    // Next index request should return empty
    $this->actingAs($user)
        ->getJson(route('events.index', $params))
        ->assertOk()
        ->assertJsonCount(0);
});

test('one user\'s event changes do not affect another user\'s cache', function () {
    $userA = createAdminUser();
    $userB = createAdminUser();

    Event::factory()->create([
        'user_id' => $userA->id,
        'starts_at' => '2026-03-10 10:00:00',
        'ends_at' => '2026-03-10 11:00:00',
    ]);

    Event::factory()->create([
        'user_id' => $userB->id,
        'starts_at' => '2026-03-10 10:00:00',
        'ends_at' => '2026-03-10 11:00:00',
    ]);

    $params = ['start' => '2026-03-01', 'end' => '2026-03-31', 'timezone' => 'UTC'];

    // Populate both users' caches
    $this->actingAs($userA)->getJson(route('events.index', $params))->assertOk();
    $this->actingAs($userB)->getJson(route('events.index', $params))->assertOk();

    $versionA = Cache::get("calendar:v:{$userA->id}", 0);
    $versionB = Cache::get("calendar:v:{$userB->id}", 0);

    // User A creates a new event
    $this->actingAs($userA)
        ->postJson(route('events.store'), [
            'title' => 'A-only event',
            'start_date' => '2026-03-20',
            'start_time' => '09:00',
            'end_date' => '2026-03-20',
            'end_time' => '10:00',
            'timezone' => 'UTC',
        ])
        ->assertCreated();

    // User A's version should have changed, User B's should not
    expect(Cache::get("calendar:v:{$userA->id}", 0))->toBeGreaterThan($versionA);
    expect(Cache::get("calendar:v:{$userB->id}", 0))->toBe($versionB);
});

// ─── CRUD & Auth ─────────────────────────────────────────────────────

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
