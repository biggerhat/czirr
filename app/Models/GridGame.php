<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $kind
 * @property int $player_one_id
 * @property int $player_two_id
 * @property int|null $current_turn_user_id
 * @property list<int|null> $board
 * @property string $status
 * @property int|null $winner_id
 */
class GridGame extends Model
{
    protected $fillable = [
        'user_id', 'kind', 'player_one_id', 'player_two_id', 'current_turn_user_id', 'board', 'status', 'winner_id',
    ];

    protected function casts(): array
    {
        return ['board' => 'array'];
    }

    /** @return BelongsTo<User, $this> */
    public function playerOne(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player_one_id');
    }

    /** @return BelongsTo<User, $this> */
    public function playerTwo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player_two_id');
    }

    /** @return BelongsTo<User, $this> */
    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_id');
    }

    public function involves(int $userId): bool
    {
        return $this->player_one_id === $userId || $this->player_two_id === $userId;
    }
}
