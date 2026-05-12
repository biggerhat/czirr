<?php

namespace App\Services;

class YahtzeeScoreService
{
    public const UPPER_CATEGORIES = ['ones', 'twos', 'threes', 'fours', 'fives', 'sixes'];

    public const LOWER_CATEGORIES = [
        'three_of_a_kind',
        'four_of_a_kind',
        'full_house',
        'small_straight',
        'large_straight',
        'yahtzee',
        'chance',
    ];

    public const ALL_CATEGORIES = [
        'ones', 'twos', 'threes', 'fours', 'fives', 'sixes',
        'three_of_a_kind', 'four_of_a_kind', 'full_house',
        'small_straight', 'large_straight', 'yahtzee', 'chance',
    ];

    public const UPPER_BONUS_THRESHOLD = 63;

    public const UPPER_BONUS_VALUE = 35;

    public const YAHTZEE_BONUS_VALUE = 100;

    public function emptyScorecard(): array
    {
        $card = [];
        foreach (self::ALL_CATEGORIES as $cat) {
            $card[$cat] = null;
        }
        $card['yahtzee_bonus'] = 0;

        return $card;
    }

    /**
     * @param  int[]  $dice  five integers 1..6
     */
    public function scoreFor(string $category, array $dice): int
    {
        $counts = array_count_values($dice);
        $sum = array_sum($dice);

        return match ($category) {
            'ones' => ($counts[1] ?? 0) * 1,
            'twos' => ($counts[2] ?? 0) * 2,
            'threes' => ($counts[3] ?? 0) * 3,
            'fours' => ($counts[4] ?? 0) * 4,
            'fives' => ($counts[5] ?? 0) * 5,
            'sixes' => ($counts[6] ?? 0) * 6,
            'three_of_a_kind' => max($counts) >= 3 ? $sum : 0,
            'four_of_a_kind' => max($counts) >= 4 ? $sum : 0,
            'full_house' => $this->isFullHouse($counts) ? 25 : 0,
            'small_straight' => $this->hasStraight($dice, 4) ? 30 : 0,
            'large_straight' => $this->hasStraight($dice, 5) ? 40 : 0,
            'yahtzee' => max($counts) === 5 ? 50 : 0,
            'chance' => $sum,
            default => 0,
        };
    }

    /**
     * Return preview scores for every still-open category.
     *
     * @param  int[]  $dice
     * @return array<string,int>
     */
    public function previewAll(array $dice, array $scorecard): array
    {
        $preview = [];
        foreach (self::ALL_CATEGORIES as $cat) {
            if ($scorecard[$cat] === null) {
                $preview[$cat] = $this->scoreFor($cat, $dice);
            }
        }

        return $preview;
    }

    public function upperTotal(array $scorecard): int
    {
        $total = 0;
        foreach (self::UPPER_CATEGORIES as $cat) {
            $total += (int) ($scorecard[$cat] ?? 0);
        }

        return $total;
    }

    public function upperBonus(array $scorecard): int
    {
        return $this->upperTotal($scorecard) >= self::UPPER_BONUS_THRESHOLD
            ? self::UPPER_BONUS_VALUE
            : 0;
    }

    public function lowerTotal(array $scorecard): int
    {
        $total = 0;
        foreach (self::LOWER_CATEGORIES as $cat) {
            $total += (int) ($scorecard[$cat] ?? 0);
        }

        return $total;
    }

    public function grandTotal(array $scorecard): int
    {
        return $this->upperTotal($scorecard)
            + $this->upperBonus($scorecard)
            + $this->lowerTotal($scorecard)
            + (int) ($scorecard['yahtzee_bonus'] ?? 0);
    }

    public function isComplete(array $scorecard): bool
    {
        foreach (self::ALL_CATEGORIES as $cat) {
            if ($scorecard[$cat] === null) {
                return false;
            }
        }

        return true;
    }

    private function isFullHouse(array $counts): bool
    {
        $values = array_values($counts);
        sort($values);

        return $values === [2, 3];
    }

    /**
     * @param  int[]  $dice
     */
    private function hasStraight(array $dice, int $length): bool
    {
        $unique = array_unique($dice);
        sort($unique);
        $unique = array_values($unique);

        $run = 1;
        for ($i = 1; $i < count($unique); $i++) {
            if ($unique[$i] === $unique[$i - 1] + 1) {
                $run++;
                if ($run >= $length) {
                    return true;
                }
            } else {
                $run = 1;
            }
        }

        return false;
    }
}
