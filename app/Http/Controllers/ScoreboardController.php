<?php

namespace App\Http\Controllers;

use App\Models\BonusObjective;
use App\Models\StreakBonus;
use App\Services\ChoreScoreService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ScoreboardController extends Controller
{
    public function __construct(
        private readonly ChoreScoreService $scoreService,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $ownerId = $user->familyOwnerId();

        $weekStart = $request->query('week')
            ? Carbon::parse($request->query('week'))->startOfWeek(Carbon::MONDAY)
            : Carbon::now()->startOfWeek(Carbon::MONDAY);

        // Seed default streak bonuses if none exist
        if (! StreakBonus::where('user_id', $ownerId)->exists()) {
            StreakBonus::insert([
                ['user_id' => $ownerId, 'days_required' => 3, 'bonus_points' => 5, 'created_at' => now(), 'updated_at' => now()],
                ['user_id' => $ownerId, 'days_required' => 7, 'bonus_points' => 15, 'created_at' => now(), 'updated_at' => now()],
                ['user_id' => $ownerId, 'days_required' => 14, 'bonus_points' => 30, 'created_at' => now(), 'updated_at' => now()],
                ['user_id' => $ownerId, 'days_required' => 30, 'bonus_points' => 75, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }

        $scoreboard = $this->scoreService->getScoreboard($ownerId, $weekStart);

        $bonusObjectives = BonusObjective::where('user_id', $ownerId)
            ->with('claimedByMember')
            ->orderByRaw('claimed_at IS NOT NULL, claimed_at DESC')
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Scoreboard', [
            'scoreboard' => $scoreboard,
            'bonusObjectives' => $bonusObjectives,
            'weekStart' => $weekStart->toDateString(),
            'can' => [
                'create' => $user->can('chores.create'),
                'edit' => $user->can('chores.edit'),
                'delete' => $user->can('chores.delete'),
            ],
        ]);
    }
}
