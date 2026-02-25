<?php

namespace App\Services;

use App\Models\BonusObjective;
use App\Models\ChoreAssignment;
use App\Models\ChoreCompletion;
use App\Models\FamilyMember;
use App\Models\StreakBonus;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ChoreScoreService
{
    /**
     * Per-member scores for a given week (Mon–Sun).
     */
    public function getWeeklyScores(int $ownerId, ?Carbon $weekStart = null): Collection
    {
        $weekStart = ($weekStart ?? Carbon::now())->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);

        $members = FamilyMember::where('user_id', $ownerId)->get();
        $memberIds = $members->pluck('id');

        $chorePoints = ChoreCompletion::whereIn('family_member_id', $memberIds)
            ->whereBetween('completed_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->selectRaw('family_member_id, SUM(points_earned) as total')
            ->groupBy('family_member_id')
            ->pluck('total', 'family_member_id');

        $bonusPoints = BonusObjective::where('user_id', $ownerId)
            ->whereNotNull('claimed_by')
            ->whereBetween('claimed_at', [$weekStart, $weekEnd->endOfDay()])
            ->selectRaw('claimed_by, SUM(points) as total')
            ->groupBy('claimed_by')
            ->pluck('total', 'claimed_by');

        $milestones = StreakBonus::where('user_id', $ownerId)
            ->orderBy('days_required')
            ->get();

        $streaks = $this->batchCalculateStreaks($members, $ownerId);

        return $members->map(function (FamilyMember $member) use ($chorePoints, $bonusPoints, $milestones, $streaks) {
            $streak = $streaks[$member->id] ?? 0;
            $streakBonus = $this->getStreakBonusesEarned($streak, $milestones);

            return [
                'family_member_id' => $member->id,
                'name' => $member->nickname ?? $member->name,
                'color' => $member->color,
                'chore_points' => (int) ($chorePoints[$member->id] ?? 0),
                'bonus_points' => (int) ($bonusPoints[$member->id] ?? 0),
                'streak_bonus' => $streakBonus,
                'weekly_total' => (int) ($chorePoints[$member->id] ?? 0) + (int) ($bonusPoints[$member->id] ?? 0) + $streakBonus,
                'streak' => $streak,
            ];
        })->sortByDesc('weekly_total')->values();
    }

    /**
     * All-time totals per member.
     */
    public function getOverallScores(int $ownerId): Collection
    {
        $members = FamilyMember::where('user_id', $ownerId)->get();

        return $this->buildOverallScores($members, $ownerId);
    }

    /**
     * Full scoreboard data — optimized to share queries between weekly + overall.
     */
    public function getScoreboard(int $ownerId, ?Carbon $weekStart = null): array
    {
        $weekStart = ($weekStart ?? Carbon::now())->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);

        // Shared queries (loaded once)
        $members = FamilyMember::where('user_id', $ownerId)->get();
        $memberIds = $members->pluck('id');

        $milestones = StreakBonus::where('user_id', $ownerId)
            ->orderBy('days_required')
            ->get();

        // Batch streak calculation (2 queries instead of up to 2920)
        $streaks = $this->batchCalculateStreaks($members, $ownerId);

        // Weekly chore points
        $weeklyChorePoints = ChoreCompletion::whereIn('family_member_id', $memberIds)
            ->whereBetween('completed_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->selectRaw('family_member_id, SUM(points_earned) as total')
            ->groupBy('family_member_id')
            ->pluck('total', 'family_member_id');

        // Weekly bonus points
        $weeklyBonusPoints = BonusObjective::where('user_id', $ownerId)
            ->whereNotNull('claimed_by')
            ->whereBetween('claimed_at', [$weekStart, $weekEnd->endOfDay()])
            ->selectRaw('claimed_by, SUM(points) as total')
            ->groupBy('claimed_by')
            ->pluck('total', 'claimed_by');

        // Build weekly scores
        $weeklyScores = $members->map(function (FamilyMember $member) use ($weeklyChorePoints, $weeklyBonusPoints, $milestones, $streaks) {
            $streak = $streaks[$member->id] ?? 0;
            $streakBonus = $this->getStreakBonusesEarned($streak, $milestones);

            return [
                'family_member_id' => $member->id,
                'name' => $member->nickname ?? $member->name,
                'color' => $member->color,
                'chore_points' => (int) ($weeklyChorePoints[$member->id] ?? 0),
                'bonus_points' => (int) ($weeklyBonusPoints[$member->id] ?? 0),
                'streak_bonus' => $streakBonus,
                'weekly_total' => (int) ($weeklyChorePoints[$member->id] ?? 0) + (int) ($weeklyBonusPoints[$member->id] ?? 0) + $streakBonus,
                'streak' => $streak,
            ];
        })->sortByDesc('weekly_total')->values()->map(function ($score, $index) {
            $score['rank'] = $index + 1;

            return $score;
        });

        // Build overall scores (reuses $members)
        $overallScores = $this->buildOverallScores($members, $ownerId);

        return [
            'weekly' => $weeklyScores,
            'overall' => $overallScores,
            'milestones' => $milestones,
        ];
    }

    /**
     * Walk backwards from yesterday counting consecutive days where the member
     * completed all their assigned chores for that day_of_week.
     *
     * Kept public for individual lookups and tests.
     */
    public function getCurrentStreak(int $familyMemberId, int $ownerId): int
    {
        $members = FamilyMember::where('id', $familyMemberId)->get();
        $streaks = $this->batchCalculateStreaks($members, $ownerId);

        return $streaks[$familyMemberId] ?? 0;
    }

    /**
     * Compare current streak against milestone rows and return total bonus points earned.
     */
    public function getStreakBonusesEarned(int $streak, Collection $milestones): int
    {
        return $milestones
            ->filter(fn ($m) => $streak >= $m->days_required)
            ->sum('bonus_points');
    }

    /**
     * Batch-calculate streaks for all given members using only 2 queries.
     *
     * @return array<int, int> member_id => streak days
     */
    private function batchCalculateStreaks(Collection $members, int $ownerId): array
    {
        $memberIds = $members->pluck('id');

        // 1 query: all active assignments for this family
        $assignments = ChoreAssignment::whereIn('family_member_id', $memberIds)
            ->whereHas('chore', fn ($q) => $q->where('user_id', $ownerId)->where('is_active', true))
            ->get(['id', 'family_member_id', 'day_of_week']);

        // Group: member_id → day_of_week → [assignment_ids]
        $assignmentMap = [];
        foreach ($assignments as $a) {
            $assignmentMap[$a->family_member_id][$a->day_of_week][] = $a->id;
        }

        // 1 query: all completions from last 365 days
        $completions = ChoreCompletion::whereIn('family_member_id', $memberIds)
            ->whereDate('completed_date', '>=', Carbon::yesterday()->subDays(365)->toDateString())
            ->get(['chore_assignment_id', 'family_member_id', 'completed_date']);

        // Group: member_id → date_string → [assignment_ids]
        $completionMap = [];
        foreach ($completions as $c) {
            $dateStr = $c->completed_date->format('Y-m-d');
            $completionMap[$c->family_member_id][$dateStr][] = $c->chore_assignment_id;
        }

        // Calculate each streak in pure PHP (zero additional queries)
        $streaks = [];
        foreach ($members as $member) {
            $streaks[$member->id] = $this->calculateStreakFromMaps(
                $assignmentMap[$member->id] ?? [],
                $completionMap[$member->id] ?? [],
            );
        }

        return $streaks;
    }

    /**
     * Compute streak from pre-loaded data — no database queries.
     */
    private function calculateStreakFromMaps(array $assignmentsByDow, array $completionsByDate): int
    {
        $date = Carbon::yesterday();
        $streak = 0;

        for ($i = 0; $i < 365; $i++) {
            $dow = $date->dayOfWeek;
            $expectedIds = $assignmentsByDow[$dow] ?? [];

            if (empty($expectedIds)) {
                $date->subDay();

                continue;
            }

            $dateStr = $date->format('Y-m-d');
            $completedIds = $completionsByDate[$dateStr] ?? [];

            // Check all expected assignments were completed
            if (count(array_intersect($expectedIds, $completedIds)) >= count($expectedIds)) {
                $streak++;
                $date->subDay();
            } else {
                break;
            }
        }

        return $streak;
    }

    /**
     * Build overall scores from pre-loaded members.
     */
    private function buildOverallScores(Collection $members, int $ownerId): Collection
    {
        $memberIds = $members->pluck('id');

        $chorePoints = ChoreCompletion::whereIn('family_member_id', $memberIds)
            ->selectRaw('family_member_id, SUM(points_earned) as total')
            ->groupBy('family_member_id')
            ->pluck('total', 'family_member_id');

        $bonusPoints = BonusObjective::where('user_id', $ownerId)
            ->whereNotNull('claimed_by')
            ->selectRaw('claimed_by, SUM(points) as total')
            ->groupBy('claimed_by')
            ->pluck('total', 'claimed_by');

        return $members->map(function (FamilyMember $member) use ($chorePoints, $bonusPoints) {
            $total = (int) ($chorePoints[$member->id] ?? 0) + (int) ($bonusPoints[$member->id] ?? 0);

            return [
                'family_member_id' => $member->id,
                'name' => $member->nickname ?? $member->name,
                'color' => $member->color,
                'total' => $total,
            ];
        })->sortByDesc('total')->values();
    }
}
