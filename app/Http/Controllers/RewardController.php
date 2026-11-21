<?php

namespace App\Http\Controllers;

use App\Models\FamilyMember;
use App\Models\Reward;
use App\Models\RewardRedemption;
use App\Models\User;
use App\Notifications\PushAlert;
use App\Services\FamilyAlerts;
use App\Services\RewardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RewardController extends Controller
{
    public function __construct(private RewardService $rewards) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $ownerId = $user->familyOwnerId();
        $canManage = $user->can('chores.edit');

        $balances = $this->rewards->balances($ownerId);
        $members = FamilyMember::where('user_id', $ownerId)->orderBy('name')->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->display_name,
                'color' => $m->color,
                'balance' => $balances[$m->id] ?? 0,
            ]);

        $redemptions = RewardRedemption::where('user_id', $ownerId)
            ->with('familyMember:id,name,nickname,color')
            ->latest()
            ->limit(50)
            ->get();

        return Inertia::render('rewards/Index', [
            'rewards' => Reward::where('user_id', $ownerId)
                ->when(! $canManage, fn ($q) => $q->where('is_active', true))
                ->orderBy('cost')->orderBy('name')->get(),
            'members' => $members,
            'myMemberId' => $this->ownMemberId($user, $ownerId),
            'pending' => $redemptions->where('status', 'pending')->values(),
            'history' => $redemptions->where('status', '!=', 'pending')->values(),
            'can' => ['manage' => $canManage],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $reward = Reward::create([
            ...$this->validated($request),
            'user_id' => $request->user()->familyOwnerId(),
        ]);

        return response()->json($reward, 201);
    }

    public function update(Request $request, Reward $reward): JsonResponse
    {
        $this->authorizeOwner($request, $reward->user_id);
        $reward->update($this->validated($request));

        return response()->json($reward);
    }

    public function destroy(Request $request, Reward $reward): JsonResponse
    {
        $this->authorizeOwner($request, $reward->user_id);
        $reward->delete();

        return response()->json(null, 204);
    }

    public function redeem(Request $request, Reward $reward): JsonResponse
    {
        $user = $request->user();
        $ownerId = $user->familyOwnerId();
        $this->authorizeOwner($request, $reward->user_id);
        abort_unless($reward->is_active, 422, 'This reward is not available.');

        $data = $request->validate(['family_member_id' => ['required', 'integer']]);
        $member = FamilyMember::where('id', $data['family_member_id'])->where('user_id', $ownerId)->firstOrFail();

        // Kids may only spend their own points; managers can redeem for anyone.
        if (! $user->can('chores.edit')) {
            abort_unless($member->id === $this->ownMemberId($user, $ownerId), 403);
        }

        $redemption = DB::transaction(function () use ($ownerId, $reward, $member) {
            $balance = $this->rewards->balances($ownerId)[$member->id] ?? 0;
            abort_if($balance < $reward->cost, 422, 'Not enough points.');

            return RewardRedemption::create([
                'user_id' => $ownerId,
                'reward_id' => $reward->id,
                'family_member_id' => $member->id,
                'reward_name' => $reward->name,
                'cost' => $reward->cost,
            ]);
        });

        $this->notifyManagers($user, $ownerId, $member, $reward);

        return response()->json($redemption, 201);
    }

    public function resolve(Request $request, RewardRedemption $redemption): JsonResponse
    {
        $this->authorizeOwner($request, $redemption->user_id);
        abort_unless($redemption->status === 'pending', 422, 'Already resolved.');

        $data = $request->validate(['status' => ['required', 'in:approved,denied']]);
        $redemption->update(['status' => $data['status'], 'resolved_at' => now()]);

        $recipient = $redemption->familyMember?->linkedUser;
        if ($recipient && $recipient->id !== $request->user()->id) {
            $approved = $data['status'] === 'approved';
            $recipient->notify(new PushAlert(
                $approved ? 'Reward approved! 🎉' : 'Reward not approved',
                $approved ? "Enjoy: {$redemption->reward_name}" : "{$redemption->reward_name} was declined — your points were refunded.",
                '/rewards',
                'reward-result',
            ));
        }

        return response()->json($redemption);
    }

    private function notifyManagers(User $requester, int $ownerId, FamilyMember $member, Reward $reward): void
    {
        app(FamilyAlerts::class)->notifyManagers($ownerId, $requester->id, new PushAlert(
            'Reward request',
            "{$member->display_name} wants: {$reward->name} ({$reward->cost} pts)",
            '/rewards',
            'reward-request',
        ));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'cost' => ['required', 'integer', 'min:1', 'max:100000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    private function authorizeOwner(Request $request, int $resourceOwnerId): void
    {
        abort_unless($resourceOwnerId === $request->user()->familyOwnerId(), 403);
    }

    private function ownMemberId($user, int $ownerId): ?int
    {
        return FamilyMember::where('user_id', $ownerId)->where('linked_user_id', $user->id)->value('id');
    }
}
