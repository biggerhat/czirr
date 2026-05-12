<?php

namespace App\Http\Controllers;

use App\Models\FamilyMember;
use App\Models\User;
use App\Models\YahtzeeGame;
use App\Services\YahtzeeScoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class YahtzeeGameController extends Controller
{
    public function __construct(private readonly YahtzeeScoreService $scoreService) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $ownerId = $user->familyOwnerId();

        $games = YahtzeeGame::where('user_id', $ownerId)
            ->where(function ($q) use ($user) {
                $q->where('player_one_id', $user->id)
                    ->orWhere('player_two_id', $user->id);
            })
            ->with(['playerOne:id,name', 'playerTwo:id,name', 'currentTurnUser:id,name', 'winner:id,name'])
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderByDesc('updated_at')
            ->get();

        $playerIds = $games
            ->flatMap(fn ($g) => [$g->player_one_id, $g->player_two_id])
            ->unique()
            ->push($user->id)
            ->all();

        return Inertia::render('yahtzee/Index', [
            'games' => $games,
            'opponents' => $this->availableOpponents($user),
            'currentUserId' => $user->id,
            'playerColors' => $this->resolvePlayerColors($ownerId, $playerIds),
        ]);
    }

    public function show(Request $request, YahtzeeGame $yahtzeeGame): Response
    {
        $this->authorizeAccess($request, $yahtzeeGame);

        $yahtzeeGame->load(['playerOne:id,name', 'playerTwo:id,name', 'currentTurnUser:id,name', 'winner:id,name']);

        $preview = $yahtzeeGame->status === 'active' && $yahtzeeGame->current_turn_user_id === $request->user()->id && $yahtzeeGame->rolls_left < 3
            ? $this->scoreService->previewAll(
                $this->diceValues($yahtzeeGame->dice),
                $yahtzeeGame->scorecards[$yahtzeeGame->current_turn_user_id] ?? $this->scoreService->emptyScorecard()
            )
            : [];

        $totals = [];
        foreach ([$yahtzeeGame->player_one_id, $yahtzeeGame->player_two_id] as $pid) {
            $card = $yahtzeeGame->scorecards[$pid] ?? $this->scoreService->emptyScorecard();
            $totals[$pid] = [
                'upper' => $this->scoreService->upperTotal($card),
                'upper_bonus' => $this->scoreService->upperBonus($card),
                'lower' => $this->scoreService->lowerTotal($card),
                'yahtzee_bonus' => (int) ($card['yahtzee_bonus'] ?? 0),
                'grand' => $this->scoreService->grandTotal($card),
            ];
        }

        return Inertia::render('yahtzee/Show', [
            'game' => $yahtzeeGame,
            'preview' => $preview,
            'totals' => $totals,
            'currentUserId' => $request->user()->id,
            'playerColors' => $this->resolvePlayerColors(
                $yahtzeeGame->user_id,
                [$yahtzeeGame->player_one_id, $yahtzeeGame->player_two_id],
            ),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'opponent_id' => ['required', 'integer', Rule::in($this->availableOpponents($user)->pluck('id')->all())],
        ]);

        $game = YahtzeeGame::create([
            'user_id' => $user->familyOwnerId(),
            'player_one_id' => $user->id,
            'player_two_id' => $validated['opponent_id'],
            'current_turn_user_id' => $user->id,
            'dice' => $this->freshDice(),
            'rolls_left' => 3,
            'scorecards' => [
                $user->id => $this->scoreService->emptyScorecard(),
                $validated['opponent_id'] => $this->scoreService->emptyScorecard(),
            ],
            'status' => 'active',
        ]);

        return response()->json(['id' => $game->id], 201);
    }

    public function roll(Request $request, YahtzeeGame $yahtzeeGame): JsonResponse
    {
        $this->authorizeAccess($request, $yahtzeeGame);
        $this->assertActiveTurn($request, $yahtzeeGame);

        if ($yahtzeeGame->rolls_left < 1) {
            abort(422, 'No rolls left this turn.');
        }

        $dice = $yahtzeeGame->dice;
        foreach ($dice as &$die) {
            if (! ($die['held'] ?? false)) {
                $die['value'] = random_int(1, 6);
            }
        }
        unset($die);

        $yahtzeeGame->update([
            'dice' => $dice,
            'rolls_left' => $yahtzeeGame->rolls_left - 1,
        ]);

        return response()->json(null, 204);
    }

    public function hold(Request $request, YahtzeeGame $yahtzeeGame): JsonResponse
    {
        $this->authorizeAccess($request, $yahtzeeGame);
        $this->assertActiveTurn($request, $yahtzeeGame);

        $validated = $request->validate([
            'index' => ['required', 'integer', 'min:0', 'max:4'],
        ]);

        if ($yahtzeeGame->rolls_left === 3) {
            abort(422, 'Roll the dice before holding.');
        }

        $dice = $yahtzeeGame->dice;
        $dice[$validated['index']]['held'] = ! ($dice[$validated['index']]['held'] ?? false);
        $yahtzeeGame->update(['dice' => $dice]);

        return response()->json(null, 204);
    }

    public function score(Request $request, YahtzeeGame $yahtzeeGame): JsonResponse
    {
        $this->authorizeAccess($request, $yahtzeeGame);
        $this->assertActiveTurn($request, $yahtzeeGame);

        $validated = $request->validate([
            'category' => ['required', 'string', Rule::in(YahtzeeScoreService::ALL_CATEGORIES)],
        ]);

        if ($yahtzeeGame->rolls_left === 3) {
            abort(422, 'Roll the dice before scoring.');
        }

        $userId = $request->user()->id;
        $scorecards = $yahtzeeGame->scorecards;
        $card = $scorecards[$userId] ?? $this->scoreService->emptyScorecard();

        if ($card[$validated['category']] !== null) {
            abort(422, 'That category is already filled.');
        }

        $diceValues = $this->diceValues($yahtzeeGame->dice);
        $score = $this->scoreService->scoreFor($validated['category'], $diceValues);

        // Yahtzee bonus: if rolling a Yahtzee while Yahtzee category was already filled with 50.
        $counts = array_count_values($diceValues);
        $isYahtzeeRoll = (max($counts) === 5);
        if ($isYahtzeeRoll && ($card['yahtzee'] ?? null) === 50 && $validated['category'] !== 'yahtzee') {
            $card['yahtzee_bonus'] = (int) ($card['yahtzee_bonus'] ?? 0) + YahtzeeScoreService::YAHTZEE_BONUS_VALUE;
        }

        $card[$validated['category']] = $score;
        $scorecards[$userId] = $card;

        $update = [
            'scorecards' => $scorecards,
            'dice' => $this->freshDice(),
            'rolls_left' => 3,
        ];

        $otherId = $yahtzeeGame->otherPlayerId($userId);
        $otherCard = $scorecards[$otherId] ?? $this->scoreService->emptyScorecard();

        if ($this->scoreService->isComplete($card) && $this->scoreService->isComplete($otherCard)) {
            $update['status'] = 'completed';
            $myTotal = $this->scoreService->grandTotal($card);
            $otherTotal = $this->scoreService->grandTotal($otherCard);
            $update['winner_id'] = $myTotal === $otherTotal
                ? null
                : ($myTotal > $otherTotal ? $userId : $otherId);
        } else {
            // Hand turn to the other player (unless they already finished their card).
            $update['current_turn_user_id'] = $this->scoreService->isComplete($otherCard) ? $userId : $otherId;
        }

        $yahtzeeGame->update($update);

        return response()->json(null, 204);
    }

    public function destroy(Request $request, YahtzeeGame $yahtzeeGame): JsonResponse
    {
        $this->authorizeAccess($request, $yahtzeeGame);

        $yahtzeeGame->delete();

        return response()->json(null, 204);
    }

    private function authorizeAccess(Request $request, YahtzeeGame $game): void
    {
        $user = $request->user();
        if ($game->user_id !== $user->familyOwnerId() || ! $game->involves($user->id)) {
            abort(403);
        }
    }

    private function assertActiveTurn(Request $request, YahtzeeGame $game): void
    {
        if ($game->status !== 'active') {
            abort(422, 'This game is already over.');
        }
        if ($game->current_turn_user_id !== $request->user()->id) {
            abort(403, "It's not your turn.");
        }
    }

    private function freshDice(): array
    {
        return array_fill(0, 5, ['value' => 1, 'held' => false]);
    }

    /**
     * @return int[]
     */
    private function diceValues(array $dice): array
    {
        return array_map(fn ($d) => (int) $d['value'], $dice);
    }

    /**
     * @param  int[]  $userIds
     * @return array<int,string> user id => Tailwind color name
     */
    private function resolvePlayerColors(int $ownerId, array $userIds): array
    {
        $palette = ['blue', 'emerald', 'violet', 'amber', 'rose', 'cyan', 'orange', 'pink'];

        $dbColors = FamilyMember::where('user_id', $ownerId)
            ->whereIn('linked_user_id', $userIds)
            ->whereNotNull('color')
            ->pluck('color', 'linked_user_id')
            ->all();

        $result = [];
        foreach ($userIds as $id) {
            $result[$id] = $dbColors[$id] ?? $palette[$id % count($palette)];
        }

        return $result;
    }

    private function availableOpponents(User $user)
    {
        $ownerId = $user->familyOwnerId();
        $owner = $user->familyOwner();

        $linkedUserIds = FamilyMember::where('user_id', $ownerId)
            ->whereNotNull('linked_user_id')
            ->pluck('linked_user_id');

        $candidateIds = $linkedUserIds
            ->push($owner->id)
            ->unique()
            ->reject(fn ($id) => $id === $user->id)
            ->values();

        return User::whereIn('id', $candidateIds)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();
    }
}
