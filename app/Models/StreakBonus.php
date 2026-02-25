<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StreakBonus extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'days_required',
        'bonus_points',
    ];

    protected function casts(): array
    {
        return [
            'days_required' => 'integer',
            'bonus_points' => 'integer',
        ];
    }
}
