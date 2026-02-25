<?php

use App\Models\BonusObjective;
use App\Models\Chore;
use App\Models\ChoreAssignment;
use App\Models\ChoreCompletion;
use App\Models\FamilyMember;
use App\Models\StreakBonus;
use App\Services\ChoreScoreService;
use Carbon\Carbon;

function setupScoreTestData(): array
{
    $user = createAdminUser();
    $member = FamilyMember::where('user_id', $user->id)->first();
    $service = app(ChoreScoreService::class);

    return [$user, $member, $service];
}

test('weekly scores aggregate chore completion points correctly', function () {
    [$user, $member, $service] = setupScoreTestData();

    $chore = Chore::factory()->create(['user_id' => $user->id, 'points' => 10]);
    $assignment = ChoreAssignment::factory()->create([
        'chore_id' => $chore->id,
        'family_member_id' => $member->id,
        'day_of_week' => 1,
    ]);

    $weekStart = Carbon::now()->startOfWeek(Carbon::MONDAY);

    // Create completions on Mon and Tue of this week
    ChoreCompletion::create([
        'chore_assignment_id' => $assignment->id,
        'family_member_id' => $member->id,
        'completed_date' => $weekStart->toDateString(),
        'points_earned' => 10,
    ]);
    ChoreCompletion::create([
        'chore_assignment_id' => $assignment->id,
        'family_member_id' => $member->id,
        'completed_date' => $weekStart->copy()->addDay()->toDateString(),
        'points_earned' => 10,
    ]);

    $scores = $service->getWeeklyScores($user->id, $weekStart);
    $memberScore = $scores->firstWhere('family_member_id', $member->id);

    expect($memberScore['chore_points'])->toBe(20);
});

test('weekly scores include bonus objective points', function () {
    [$user, $member, $service] = setupScoreTestData();

    $weekStart = Carbon::now()->startOfWeek(Carbon::MONDAY);

    BonusObjective::factory()->claimed($member)->create([
        'user_id' => $user->id,
        'points' => 30,
        'claimed_at' => $weekStart->copy()->addDays(2),
    ]);

    $scores = $service->getWeeklyScores($user->id, $weekStart);
    $memberScore = $scores->firstWhere('family_member_id', $member->id);

    expect($memberScore['bonus_points'])->toBe(30);
});

test('overall scores sum all-time totals', function () {
    [$user, $member, $service] = setupScoreTestData();

    $chore = Chore::factory()->create(['user_id' => $user->id, 'points' => 15]);
    $assignment = ChoreAssignment::factory()->create([
        'chore_id' => $chore->id,
        'family_member_id' => $member->id,
        'day_of_week' => 1,
    ]);

    // Past completions across multiple weeks
    ChoreCompletion::create([
        'chore_assignment_id' => $assignment->id,
        'family_member_id' => $member->id,
        'completed_date' => Carbon::now()->subWeeks(3)->toDateString(),
        'points_earned' => 15,
    ]);
    ChoreCompletion::create([
        'chore_assignment_id' => $assignment->id,
        'family_member_id' => $member->id,
        'completed_date' => Carbon::now()->subWeeks(1)->toDateString(),
        'points_earned' => 15,
    ]);

    BonusObjective::factory()->claimed($member)->create([
        'user_id' => $user->id,
        'points' => 25,
        'claimed_at' => Carbon::now()->subWeeks(2),
    ]);

    $scores = $service->getOverallScores($user->id);
    $memberScore = $scores->firstWhere('family_member_id', $member->id);

    expect($memberScore['total'])->toBe(55); // 15 + 15 + 25
});

test('streak calculation counts consecutive days correctly', function () {
    [$user, $member, $service] = setupScoreTestData();

    $chore = Chore::factory()->create(['user_id' => $user->id, 'points' => 5]);

    // Create assignments for every day of the week
    $assignments = [];
    for ($d = 0; $d < 7; $d++) {
        $assignments[$d] = ChoreAssignment::factory()->create([
            'chore_id' => $chore->id,
            'family_member_id' => $member->id,
            'day_of_week' => $d,
        ]);
    }

    // Complete 3 consecutive days ending yesterday
    for ($i = 3; $i >= 1; $i--) {
        $date = Carbon::yesterday()->copy()->subDays($i - 1);
        $dow = $date->dayOfWeek;
        ChoreCompletion::create([
            'chore_assignment_id' => $assignments[$dow]->id,
            'family_member_id' => $member->id,
            'completed_date' => $date->toDateString(),
            'points_earned' => 5,
        ]);
    }

    $streak = $service->getCurrentStreak($member->id, $user->id);
    expect($streak)->toBe(3);
});

test('streak skips days with no assignments', function () {
    [$user, $member, $service] = setupScoreTestData();

    $chore = Chore::factory()->create(['user_id' => $user->id, 'points' => 5]);

    // Only assign on Monday (1)
    $assignment = ChoreAssignment::factory()->create([
        'chore_id' => $chore->id,
        'family_member_id' => $member->id,
        'day_of_week' => 1, // Monday
    ]);

    // Find last two Mondays before yesterday
    $lastMonday = Carbon::yesterday();
    while ($lastMonday->dayOfWeek !== 1) {
        $lastMonday->subDay();
    }
    $prevMonday = $lastMonday->copy()->subWeek();

    ChoreCompletion::create([
        'chore_assignment_id' => $assignment->id,
        'family_member_id' => $member->id,
        'completed_date' => $lastMonday->toDateString(),
        'points_earned' => 5,
    ]);
    ChoreCompletion::create([
        'chore_assignment_id' => $assignment->id,
        'family_member_id' => $member->id,
        'completed_date' => $prevMonday->toDateString(),
        'points_earned' => 5,
    ]);

    $streak = $service->getCurrentStreak($member->id, $user->id);
    expect($streak)->toBe(2);
});

test('streak breaks on incomplete day', function () {
    [$user, $member, $service] = setupScoreTestData();

    $chore = Chore::factory()->create(['user_id' => $user->id, 'points' => 5]);

    // Create assignments for every day
    $assignments = [];
    for ($d = 0; $d < 7; $d++) {
        $assignments[$d] = ChoreAssignment::factory()->create([
            'chore_id' => $chore->id,
            'family_member_id' => $member->id,
            'day_of_week' => $d,
        ]);
    }

    // Complete yesterday only (miss the day before)
    $yesterday = Carbon::yesterday();
    ChoreCompletion::create([
        'chore_assignment_id' => $assignments[$yesterday->dayOfWeek]->id,
        'family_member_id' => $member->id,
        'completed_date' => $yesterday->toDateString(),
        'points_earned' => 5,
    ]);

    // Don't complete day before yesterday -> streak should be 1
    $streak = $service->getCurrentStreak($member->id, $user->id);
    expect($streak)->toBe(1);
});

test('streak bonus milestones calculated correctly', function () {
    [$user, $member, $service] = setupScoreTestData();

    $milestones = collect([
        StreakBonus::create(['user_id' => $user->id, 'days_required' => 3, 'bonus_points' => 5]),
        StreakBonus::create(['user_id' => $user->id, 'days_required' => 7, 'bonus_points' => 15]),
        StreakBonus::create(['user_id' => $user->id, 'days_required' => 14, 'bonus_points' => 30]),
    ]);

    expect($service->getStreakBonusesEarned(0, $milestones))->toBe(0);
    expect($service->getStreakBonusesEarned(3, $milestones))->toBe(5);
    expect($service->getStreakBonusesEarned(7, $milestones))->toBe(20); // 5 + 15
    expect($service->getStreakBonusesEarned(14, $milestones))->toBe(50); // 5 + 15 + 30
    expect($service->getStreakBonusesEarned(100, $milestones))->toBe(50);
});

test('weekly scores are sorted by total descending', function () {
    $user = createAdminUser();
    $member1 = FamilyMember::where('user_id', $user->id)->first();
    $member2 = FamilyMember::factory()->create(['user_id' => $user->id]);
    $service = app(ChoreScoreService::class);

    $chore = Chore::factory()->create(['user_id' => $user->id, 'points' => 10]);
    $a1 = ChoreAssignment::factory()->create([
        'chore_id' => $chore->id,
        'family_member_id' => $member1->id,
        'day_of_week' => 1,
    ]);
    $a2 = ChoreAssignment::factory()->create([
        'chore_id' => $chore->id,
        'family_member_id' => $member2->id,
        'day_of_week' => 1,
    ]);

    $weekStart = Carbon::now()->startOfWeek(Carbon::MONDAY);

    // member2 has more points
    ChoreCompletion::create([
        'chore_assignment_id' => $a1->id,
        'family_member_id' => $member1->id,
        'completed_date' => $weekStart->toDateString(),
        'points_earned' => 10,
    ]);
    ChoreCompletion::create([
        'chore_assignment_id' => $a2->id,
        'family_member_id' => $member2->id,
        'completed_date' => $weekStart->toDateString(),
        'points_earned' => 10,
    ]);
    ChoreCompletion::create([
        'chore_assignment_id' => $a2->id,
        'family_member_id' => $member2->id,
        'completed_date' => $weekStart->copy()->addDay()->toDateString(),
        'points_earned' => 10,
    ]);

    $scores = $service->getWeeklyScores($user->id, $weekStart);
    expect($scores->first()['family_member_id'])->toBe($member2->id);
});
