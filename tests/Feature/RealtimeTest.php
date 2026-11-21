<?php

use App\Events\FamilyDataChanged;
use App\Models\Chore;
use App\Models\ChoreAssignment;
use App\Models\ChoreCompletion;
use App\Models\FamilyList;
use App\Models\FamilyListItem;
use App\Models\FamilyMember;
use Illuminate\Support\Facades\Event;

test('child records resolve their family owner for broadcasts', function () {
    Event::fake([FamilyDataChanged::class]);
    config(['broadcasting.default' => 'reverb']);
    $user = createAdminUser();
    $member = FamilyMember::where('linked_user_id', $user->id)->first();

    $list = FamilyList::factory()->create(['user_id' => $user->id]);
    FamilyListItem::factory()->create(['family_list_id' => $list->id]);
    $chore = Chore::factory()->create(['user_id' => $user->id]);
    $assignment = ChoreAssignment::factory()->create(['chore_id' => $chore->id, 'family_member_id' => $member->id, 'day_of_week' => 1]);
    ChoreCompletion::create([
        'chore_assignment_id' => $assignment->id, 'family_member_id' => $member->id,
        'completed_date' => now()->toDateString(), 'points_earned' => 1,
    ]);

    foreach (['lists', 'chores'] as $resource) {
        Event::assertDispatched(FamilyDataChanged::class, fn ($e) => $e->resource === $resource && $e->familyId === $user->id);
    }
});

test('no broadcasts are queued when realtime is off', function () {
    Event::fake([FamilyDataChanged::class]);
    $user = createAdminUser();
    FamilyList::factory()->create(['user_id' => $user->id]);

    Event::assertNotDispatched(FamilyDataChanged::class);
});

test('only the family can authorise its private channel and pages expose the family id', function () {
    Event::fake([FamilyDataChanged::class]);
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'k',
        'broadcasting.connections.reverb.secret' => 's',
        'broadcasting.connections.reverb.app_id' => '1',
    ]);
    // Channels register on the driver configured at boot (log in tests); re-register on reverb.
    require base_path('routes/channels.php');
    $user = createAdminUser();
    $other = createAdminUser();

    $this->actingAs($user)->get('/dashboard')->assertInertia(fn ($p) => $p->where('realtime.familyId', $user->id));

    $this->actingAs($user)->postJson('/broadcasting/auth', ['socket_id' => '1.2', 'channel_name' => 'private-family.'.$user->id])
        ->assertOk()->assertJsonStructure(['auth']);
    $this->actingAs($other)->postJson('/broadcasting/auth', ['socket_id' => '1.2', 'channel_name' => 'private-family.'.$user->id])
        ->assertForbidden();
});
