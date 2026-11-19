<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A scorekeeper for any family game ("game night") plus a Farkle preset.
 *
 * @property int $id
 * @property int $user_id
 * @property string $title
 * @property string $kind
 * @property list<array{name: string, family_member_id?: int|null}> $players
 * @property list<list<int>> $rounds
 * @property int|null $target_score
 * @property bool $low_wins
 * @property string $status
 * @property string|null $winner_names
 */
class ScoreGame extends Model
{
    public const KINDS = ['scorekeeper', 'farkle'];

    protected $fillable = [
        'created_by', 'title', 'kind', 'players', 'rounds', 'target_score', 'low_wins', 'status', 'winner_names',
    ];

    protected function casts(): array
    {
        return ['players' => 'array', 'rounds' => 'array', 'low_wins' => 'boolean', 'target_score' => 'integer'];
    }

    /** @return list<int> */
    public function totals(): array
    {
        $totals = array_fill(0, count($this->players), 0);
        foreach ($this->rounds as $round) {
            foreach ($round as $i => $score) {
                $totals[$i] += (int) $score;
            }
        }

        return $totals;
    }

    /** Names of the player(s) currently winning (ties allowed). */
    public function leaderNames(): string
    {
        $totals = $this->totals();
        $best = $this->low_wins ? min($totals) : max($totals);

        return collect($this->players)
            ->filter(fn ($p, $i) => $totals[$i] === $best)
            ->pluck('name')
            ->implode(' & ');
    }

    public function targetReached(): bool
    {
        return $this->target_score !== null && max($this->totals()) >= $this->target_score;
    }
}
