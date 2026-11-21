<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $family_member_id
 * @property string|null $name
 * @property string $role
 * @property \Carbon\Carbon $expires_at
 * @property \Carbon\Carbon|null $accepted_at
 */
class FamilyInvite extends Model
{
    public const TTL_DAYS = 7;

    protected $fillable = [
        'user_id', 'created_by', 'family_member_id', 'name', 'role', 'token_hash', 'expires_at',
        'accepted_at', 'accepted_by',
    ];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'accepted_at' => 'datetime'];
    }

    /**
     * Create an invite and return it with the one-time plain token. Only a hash
     * is stored, so a leaked database can't be used to join a family.
     *
     * @param  array<string, mixed>  $attributes
     * @return array{0: self, 1: string}
     */
    public static function issue(array $attributes): array
    {
        $token = Str::random(48);

        $invite = static::create([
            ...$attributes,
            'token_hash' => static::hash($token),
            'expires_at' => now()->addDays(self::TTL_DAYS),
        ]);

        return [$invite, $token];
    }

    public static function findByToken(string $token): ?self
    {
        return static::where('token_hash', static::hash($token))->first();
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public function isUsable(): bool
    {
        return $this->accepted_at === null && $this->expires_at->isFuture();
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<FamilyMember, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(FamilyMember::class, 'family_member_id');
    }
}
