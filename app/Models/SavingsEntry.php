<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line in a family member's savings account ("kid bank"). Signed amount:
 * positive adds money owed/saved, negative is a payout or spend.
 *
 * @property int $id
 * @property int $user_id
 * @property int $family_member_id
 * @property numeric-string $amount
 * @property string|null $note
 */
class SavingsEntry extends Model
{
    protected $fillable = ['family_member_id', 'amount', 'note', 'entry_date', 'created_by'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'entry_date' => 'date'];
    }

    /** @return BelongsTo<FamilyMember, $this> */
    public function familyMember(): BelongsTo
    {
        return $this->belongsTo(FamilyMember::class);
    }
}
