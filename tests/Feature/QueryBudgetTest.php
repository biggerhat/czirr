<?php

use App\Models\Chore;
use App\Models\ChoreAssignment;
use App\Models\FamilyList;
use App\Models\FamilyListItem;
use App\Models\FamilyMember;
use App\Models\Recipe;
use Illuminate\Support\Facades\DB;

function queryCount(string $url): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    test()->get($url)->assertSuccessful();
    $n = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $n;
}

function seedFamily(\App\Models\User $user, int $n): void
{
    $member = FamilyMember::where('linked_user_id', $user->id)->first();
    for ($i = 0; $i < $n; $i++) {
        $list = FamilyList::factory()->create(['user_id' => $user->id]);
        FamilyListItem::factory()->count(3)->create(['family_list_id' => $list->id]);
        $chore = Chore::factory()->create(['user_id' => $user->id]);
        ChoreAssignment::factory()->create(['chore_id' => $chore->id, 'family_member_id' => $member->id, 'day_of_week' => $i % 7]);
        Recipe::factory()->create(['user_id' => $user->id]);
    }
}

// Page query counts must not grow with the amount of data (N+1 guard).
test('busy pages do not run more queries as data grows', function () {
    $user = createAdminUser();
    $this->actingAs($user);
    $urls = ['/dashboard', '/chores', '/lists', '/recipes', '/scoreboard', '/my-day', '/meal-plans', '/calendar'];

    seedFamily($user, 2);
    $small = collect($urls)->mapWithKeys(fn ($u) => [$u => queryCount($u)]);
    seedFamily($user, 10);
    $large = collect($urls)->mapWithKeys(fn ($u) => [$u => queryCount($u)]);

    foreach ($urls as $u) {
        expect($large[$u])->toBeLessThanOrEqual($small[$u] + 3, "$u: {$small[$u]} -> {$large[$u]} queries");
    }
});
