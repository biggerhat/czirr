<?php

namespace App\Http\Controllers;

use App\Models\ChoreAssignment;
use App\Models\ChoreCompletion;
use App\Models\FamilyMember;
use App\Services\ChoreScoreService;
use App\Services\RewardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MyDayController extends Controller
{
    public function __construct(
        private ChoreScoreService $scores,
        private RewardService $rewards,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $ownerId = $user->familyOwnerId();
        $canManage = $user->can('chores.edit');

        $members = FamilyMember::where('user_id', $ownerId)->orderBy('name')->get();
        $own = $members->firstWhere('linked_user_id', $user->id);

        // Parents may view anyone's day (e.g. on a shared kitchen tablet).
        $requested = $canManage && $request->query('member')
            ? $members->firstWhere('id', (int) $request->query('member'))
            : null;
        $member = $requested ?? $own ?? ($canManage ? $members->first() : null);

        $today = $user->localNow()->startOfDay();

        $chores = collect();
        $stats = null;
        if ($member) {
            $assignments = ChoreAssignment::where('family_member_id', $member->id)
                ->where('day_of_week', $today->dayOfWeek)
                ->whereHas('chore', fn ($q) => $q->where('user_id', $ownerId)->where('is_active', true))
                ->with('chore:id,name,description,points')
                ->get();

            // Pending completions still show as ticked (awaiting a parent) but earn nothing yet.
            $done = ChoreCompletion::withoutGlobalScope('approved')
                ->whereIn('chore_assignment_id', $assignments->pluck('id'))
                ->whereDate('completed_date', $today->toDateString())
                ->pluck('status', 'chore_assignment_id');

            $chores = $assignments->map(fn ($a) => [
                'assignment_id' => $a->id,
                'name' => $a->chore->name,
                'description' => $a->chore->description,
                'points' => $a->chore->points,
                'completed' => $done->has($a->id),
                'pending' => $done->get($a->id) === ChoreCompletion::PENDING,
            ])->values();

            $weekly = $this->scores->getWeeklyScores($ownerId)->firstWhere('family_member_id', $member->id);
            $stats = [
                'streak' => $weekly['streak'] ?? 0,
                'weekly_total' => $weekly['weekly_total'] ?? 0,
                'balance' => $this->rewards->balances($ownerId)[$member->id] ?? 0,
            ];
        }

        return Inertia::render('my-day/Index', [
            'member' => $member?->only(['id', 'name', 'nickname', 'color']),
            'members' => $canManage ? $members->map->only(['id', 'name', 'nickname', 'color'])->values() : [],
            'chores' => $chores,
            'stats' => $stats,
            'date' => $today->toDateString(),
        ]);
    }
}
