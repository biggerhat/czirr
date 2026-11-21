<?php

namespace App\Services;

use App\Models\RewardRedemption;

class RewardService
{
    public function __construct(private ChoreScoreService $scores) {}

    /**
     * Spendable balance per family member: all-time earned points minus
     * everything pending or approved. Denied redemptions are refunded.
     *
     * @return array<int, int> family_member_id => balance
     */
    public function balances(int $ownerId): array
    {
        $spent = RewardRedemption::where('user_id', $ownerId)
            ->whereIn('status', ['pending', 'approved'])
            ->selectRaw('family_member_id, SUM(cost) as total')
            ->groupBy('family_member_id')
            ->pluck('total', 'family_member_id');

        return $this->scores->getOverallScores($ownerId)
            ->mapWithKeys(fn ($s) => [
                $s['family_member_id'] => $s['total'] - (int) ($spent[$s['family_member_id']] ?? 0),
            ])
            ->all();
    }
}
