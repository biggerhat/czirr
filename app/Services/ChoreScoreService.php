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

        // Chore completion points per member this week
        $chorePoints = ChoreCompletion::whereIn('family_member_id', $members->pluck('id'))
            ->whereBetween('completed_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->selectRaw('family_member_id, SUM(points_earned) as total')
            ->groupBy('family_member_id')
            ->pluck('total', 'family_member_id');

        // Bonus objective points claimed this week
        $bonusPoints = BonusObjective::where('user_id', $ownerId)
            ->whereNotNull('claimed_by')
            ->whereBetween('claimed_at', [$weekStart, $weekEnd->endOfDay()])
            ->selectRaw('claimed_by, SUM(points) as total')
            ->groupBy('claimed_by')
            ->pluck('total', 'claimed_by');

        // Streak bonuses
        $milestones = StreakBonus::where('user_id', $ownerId)
            ->orderBy('days_required')
            ->get();

        return $members->map(function (FamilyMember $member) use ($chorePoints, $bonusPoints, $milestones, $ownerId) {
            $streak = $this->getCurrentStreak($member->id, $ownerId);
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

        $chorePoints = ChoreCompletion::whereIn('family_member_id', $members->pluck('id'))
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

    /**
     * Walk backwards from yesterday counting consecutive days where the member
     * completed all their assigned chores for that day_of_week.
     */
    public function getCurrentStreak(int $familyMemberId, int $ownerId): int
    {
        $date = Carbon::yesterday();
        $streak = 0;

        for ($i = 0; $i < 365; $i++) {
            $dayOfWeek = $date->dayOfWeek;

            // Get all assignments for this member on this day_of_week
            $assignments = ChoreAssignment::where('family_member_id', $familyMemberId)
                ->where('day_of_week', $dayOfWeek)
                ->whereHas('chore', fn ($q) => $q->where('user_id', $ownerId)->where('is_active', true))
                ->pluck('id');

            // No assignments on this day means skip (don't break streak)
            if ($assignments->isEmpty()) {
                $date->subDay();

                continue;
            }

            // Check if all assignments were completed on this date
            $completions = ChoreCompletion::where('family_member_id', $familyMemberId)
                ->whereIn('chore_assignment_id', $assignments)
                ->whereDate('completed_date', $date->toDateString())
                ->count();

            if ($completions >= $assignments->count()) {
                $streak++;
                $date->subDay();
            } else {
                break;
            }
        }

        return $streak;
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
     * Full scoreboard data.
     */
    public function getScoreboard(int $ownerId, ?Carbon $weekStart = null): array
    {
        $weeklyScores = $this->getWeeklyScores($ownerId, $weekStart);
        $overallScores = $this->getOverallScores($ownerId);

        $milestones = StreakBonus::where('user_id', $ownerId)
            ->orderBy('days_required')
            ->get();

        // Add rank to weekly scores
        $weeklyScores = $weeklyScores->values()->map(function ($score, $index) {
            $score['rank'] = $index + 1;

            return $score;
        });

        return [
            'weekly' => $weeklyScores,
            'overall' => $overallScores,
            'milestones' => $milestones,
        ];
    }
}
