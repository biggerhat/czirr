<?php

namespace App\Http\Controllers;

use App\Models\FamilyMember;
use App\Models\ScoreGame;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Game night: a generic scorekeeper and a Farkle preset. */
class ScoreGameController extends Controller
{
    public function index(Request $request): Response
    {
        $owner = $request->user()->familyOwner();

        $games = ScoreGame::where('user_id', $owner->id)
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderByDesc('updated_at')
            ->limit(60)
            ->get()
            ->map(fn (ScoreGame $g) => $this->summary($g));

        $wins = [];
        foreach (ScoreGame::where('user_id', $owner->id)->where('status', 'completed')->whereNotNull('winner_names')->pluck('winner_names') as $names) {
            foreach (explode(' & ', $names) as $name) {
                $wins[$name] = ($wins[$name] ?? 0) + 1;
            }
        }
        arsort($wins);

        return Inertia::render('games/Index', [
            'games' => $games,
            'leaderboard' => collect($wins)->map(fn ($n, $name) => ['name' => $name, 'wins' => $n])->values()->take(8),
            'members' => FamilyMember::where('user_id', $owner->id)->orderBy('name')->get(['id', 'name', 'nickname'])
                ->map(fn (FamilyMember $m) => ['id' => $m->id, 'name' => $m->display_name]),
            'canCreate' => $request->user()->can('lists.create'),
        ]);
    }

    public function show(Request $request, ScoreGame $scoreGame): Response
    {
        $this->authorizeGame($request, $scoreGame);

        return Inertia::render('games/Show', [
            'game' => $this->summary($scoreGame) + ['rounds' => $scoreGame->rounds],
            'canEdit' => $request->user()->can('lists.create'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $owner = $request->user()->familyOwner();

        $data = $request->validate([
            'title' => ['required', 'string', 'max:80'],
            'kind' => ['required', Rule::in(ScoreGame::KINDS)],
            'players' => ['required', 'array', 'min:2', 'max:8'],
            'players.*.name' => ['required', 'string', 'max:40'],
            'players.*.family_member_id' => ['nullable', 'integer', Rule::exists('family_members', 'id')->where('user_id', $owner->id)],
            'target_score' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'low_wins' => ['boolean'],
        ]);

        $farkle = $data['kind'] === 'farkle';

        /** @var ScoreGame $game */
        $game = $owner->scoreGames()->create([
            'created_by' => $request->user()->id,
            'title' => $data['title'],
            'kind' => $data['kind'],
            'players' => collect($data['players'])->map(fn ($p) => [
                'name' => $p['name'],
                'family_member_id' => $p['family_member_id'] ?? null,
            ])->values()->all(),
            'rounds' => [],
            'target_score' => $farkle ? ($data['target_score'] ?? 10000) : ($data['target_score'] ?? null),
            'low_wins' => $farkle ? false : (bool) ($data['low_wins'] ?? false),
        ]);

        return response()->json(['id' => $game->id], 201);
    }

    public function addRound(Request $request, ScoreGame $scoreGame): JsonResponse
    {
        $this->authorizeGame($request, $scoreGame);
        $this->assertActive($scoreGame);

        $count = count($scoreGame->players);
        $data = $request->validate([
            'scores' => ['required', 'array', 'size:'.$count],
            'scores.*' => ['required', 'integer', 'min:-100000', 'max:100000'],
        ]);

        $rounds = $scoreGame->rounds;
        $rounds[] = array_map('intval', array_values($data['scores']));
        $scoreGame->rounds = $rounds;

        if ($scoreGame->targetReached()) {
            $scoreGame->status = 'completed';
            $scoreGame->winner_names = $scoreGame->leaderNames();
        }
        $scoreGame->save();

        return response()->json(null, 204);
    }

    public function undoRound(Request $request, ScoreGame $scoreGame): JsonResponse
    {
        $this->authorizeGame($request, $scoreGame);

        $rounds = $scoreGame->rounds;
        array_pop($rounds);
        $scoreGame->fill(['rounds' => $rounds, 'status' => 'active', 'winner_names' => null])->save();

        return response()->json(null, 204);
    }

    public function finish(Request $request, ScoreGame $scoreGame): JsonResponse
    {
        $this->authorizeGame($request, $scoreGame);
        $this->assertActive($scoreGame);

        $scoreGame->fill(['status' => 'completed', 'winner_names' => $scoreGame->rounds ? $scoreGame->leaderNames() : null])->save();

        return response()->json(null, 204);
    }

    public function destroy(Request $request, ScoreGame $scoreGame): JsonResponse
    {
        $this->authorizeGame($request, $scoreGame);
        $scoreGame->delete();

        return response()->json(null, 204);
    }

    private function authorizeGame(Request $request, ScoreGame $game): void
    {
        abort_unless($game->user_id === $request->user()->familyOwnerId(), 403);
    }

    private function assertActive(ScoreGame $game): void
    {
        abort_if($game->status !== 'active', 422, 'This game is already over.');
    }

    /** @return array<string, mixed> */
    private function summary(ScoreGame $g): array
    {
        return [
            'id' => $g->id,
            'title' => $g->title,
            'kind' => $g->kind,
            'players' => $g->players,
            'totals' => $g->totals(),
            'round_count' => count($g->rounds),
            'target_score' => $g->target_score,
            'low_wins' => $g->low_wins,
            'status' => $g->status,
            'winner_names' => $g->winner_names,
            'updated_at' => $g->updated_at?->toIso8601String(),
        ];
    }
}
