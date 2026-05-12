<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class YahtzeeGame extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'player_one_id',
        'player_two_id',
        'current_turn_user_id',
        'dice',
        'rolls_left',
        'scorecards',
        'status',
        'winner_id',
    ];

    protected function casts(): array
    {
        return [
            'dice' => 'array',
            'scorecards' => 'array',
            'rolls_left' => 'integer',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function playerOne(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player_one_id');
    }

    public function playerTwo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player_two_id');
    }

    public function currentTurnUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_turn_user_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_id');
    }

    public function involves(int $userId): bool
    {
        return $this->player_one_id === $userId || $this->player_two_id === $userId;
    }

    public function otherPlayerId(int $userId): int
    {
        return $this->player_one_id === $userId ? $this->player_two_id : $this->player_one_id;
    }
}
