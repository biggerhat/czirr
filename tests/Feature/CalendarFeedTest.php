<?php

use App\Models\Event;
use App\Models\MealPlanEntry;

test('feed requires a valid token', function () {
    $this->get(route('calendar.feed', ['token' => str_repeat('a', 48)]))->assertNotFound();
});

test('user can create, use, rotate and disable the feed link', function () {
    $user = createAdminUser();

    $url = $this->actingAs($user)->postJson(route('calendar-feed.regenerate'))->assertOk()->json('url');
    expect($url)->toContain('/calendar/feed/')->toEndWith('.ics');

    $this->get($url)->assertOk()->assertHeader('Content-Type', 'text/calendar; charset=utf-8');

    $new = $this->actingAs($user)->postJson(route('calendar-feed.regenerate'))->json('url');
    expect($new)->not->toBe($url);
    $this->get($url)->assertNotFound();

    $this->actingAs($user)->deleteJson(route('calendar-feed.destroy'))->assertOk();
    $this->get($new)->assertNotFound();
});

test('feed contains events, recurrence rules and meals', function () {
    $user = createAdminUser();
    Event::factory()->create([
        'user_id' => $user->id,
        'title' => 'Soccer, practice; Field 2',
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHour(),
    ]);
    Event::factory()->recurring('FREQ=WEEKLY;BYDAY=MO')->create(['user_id' => $user->id, 'title' => 'Trash day']);
    MealPlanEntry::factory()->create(['user_id' => $user->id, 'date' => now()->addDay()->toDateString(), 'name' => 'Tacos']);

    $url = $this->actingAs($user)->postJson(route('calendar-feed.regenerate'))->json('url');
    $body = $this->get($url)->assertOk()->getContent();

    expect($body)
        ->toStartWith("BEGIN:VCALENDAR\r\n")
        ->toContain('SUMMARY:Soccer\\, practice\; Field 2')
        ->toContain('RRULE:FREQ=WEEKLY;BYDAY=MO')
        ->toContain(': Tacos');
});

test('bill events are hidden from users without budgeting access', function () {
    $owner = createAdminUser();
    Event::factory()->create(['user_id' => $owner->id, 'title' => 'Mortgage due', 'source' => 'bill', 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour()]);

    $url = $this->actingAs($owner)->postJson(route('calendar-feed.regenerate'))->json('url');
    expect($this->get($url)->getContent())->toContain('Mortgage due');

    $owner->syncRoles([]);
    $owner->syncPermissions(['events.view']);
    app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    expect($this->get($url)->getContent())->not->toContain('Mortgage due');
});
