<?php

namespace App\Http\Controllers;

use App\Models\FamilyMember;
use App\Models\GridGame;
use App\Models\User;
use App\Services\GridGameRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Turn-based two-player games: tic-tac-toe and Connect Four. */
class GridGameController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $games = GridGame::where('user_id', $user->familyOwnerId())
            ->where(fn ($q) => $q->where('player_one_id', $user->id)->orWhere('player_two_id', $user->id))
            ->with(['playerOne:id,name', 'playerTwo:id,name'])
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderByDesc('updated_at')
            ->limit(40)
            ->get()
            ->map(fn (GridGame $g) => $this->payload($g));

        return Inertia::render('games/Duels', [
            'games' => $games,
            'opponents' => $this->opponents($user),
            'currentUserId' => $user->id,
        ]);
    }

    public function show(Request $request, GridGame $gridGame): Response
    {
        $this->authorizeGame($request, $gridGame);

        return Inertia::render('games/Duel', [
            'game' => $this->payload($gridGame->load(['playerOne:id,name', 'playerTwo:id,name'])),
            'currentUserId' => $request->user()->id,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'kind' => ['required', Rule::in(array_keys(GridGameRules::KINDS))],
            'opponent_id' => ['required', 'integer', Rule::in($this->opponents($user)->pluck('id')->all())],
        ]);

        $game = GridGame::create([
            'user_id' => $user->familyOwnerId(),
            'kind' => $data['kind'],
            'player_one_id' => $user->id,
            'player_two_id' => $data['opponent_id'],
            'current_turn_user_id' => $user->id,
            'board' => GridGameRules::emptyBoard($data['kind']),
        ]);

        return response()->json(['id' => $game->id], 201);
    }

    public function move(Request $request, GridGame $gridGame): JsonResponse
    {
        $this->authorizeGame($request, $gridGame);
        abort_if($gridGame->status !== 'active', 422, 'This game is already over.');
        abort_if($gridGame->current_turn_user_id !== $request->user()->id, 403, "It's not your turn.");

        $data = $request->validate(['move' => ['required', 'integer', 'min:0', 'max:60']]);

        $mark = $gridGame->player_one_id === $request->user()->id ? 1 : 2;
        $board = GridGameRules::applyMove($gridGame->kind, $gridGame->board, $data['move'], $mark);
        abort_if($board === null, 422, 'That move is not allowed.');

        $gridGame->board = $board;
        if (GridGameRules::winner($gridGame->kind, $board) === $mark) {
            $gridGame->status = 'won';
            $gridGame->winner_id = $request->user()->id;
            $gridGame->current_turn_user_id = null;
        } elseif (GridGameRules::isFull($board)) {
            $gridGame->status = 'draw';
            $gridGame->current_turn_user_id = null;
        } else {
            $gridGame->current_turn_user_id = $mark === 1 ? $gridGame->player_two_id : $gridGame->player_one_id;
        }
        $gridGame->save();

        return response()->json(null, 204);
    }

    public function destroy(Request $request, GridGame $gridGame): JsonResponse
    {
        $this->authorizeGame($request, $gridGame);
        $gridGame->delete();

        return response()->json(null, 204);
    }

    private function authorizeGame(Request $request, GridGame $game): void
    {
        abort_unless($game->user_id === $request->user()->familyOwnerId() && $game->involves($request->user()->id), 403);
    }

    /** @return array<string, mixed> */
    private function payload(GridGame $g): array
    {
        return [
            'id' => $g->id,
            'kind' => $g->kind,
            'board' => $g->board,
            'cols' => GridGameRules::KINDS[$g->kind]['w'],
            'status' => $g->status,
            'player_one' => ['id' => $g->player_one_id, 'name' => $g->playerOne?->name],
            'player_two' => ['id' => $g->player_two_id, 'name' => $g->playerTwo?->name],
            'current_turn_user_id' => $g->current_turn_user_id,
            'winner_id' => $g->winner_id,
        ];
    }

    private function opponents(User $user)
    {
        $ownerId = $user->familyOwnerId();

        $ids = FamilyMember::where('user_id', $ownerId)->whereNotNull('linked_user_id')->pluck('linked_user_id')
            ->push($ownerId)->unique()->reject(fn ($id) => $id === $user->id)->values();

        return User::whereIn('id', $ids)->select('id', 'name')->orderBy('name')->get();
    }
}
