<?php

namespace App\Models;

use App\Enums\FamilyRole;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property-read User|null $linkedUser
 */
class FamilyMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'nickname',
        'role',
        'color',
        'linked_user_id',
    ];

    protected $casts = [
        'role' => FamilyRole::class,
    ];

    protected function displayName(): Attribute
    {
        return Attribute::get(fn () => $this->nickname ?? $this->name);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function linkedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'linked_user_id');
    }

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_family_member')
            ->withTimestamps();
    }
}
