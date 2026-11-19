<?php

namespace App\Models\Concerns;

use App\Enums\FamilyRole;
use App\Enums\ListVisibility;
use App\Models\FamilyMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Shared "who can see this?" rules for family-owned resources that have a
 * `visibility` column and a `members()` relation (lists, cookbooks).
 *
 * - The creator always sees their own.
 * - A linked family member sees the owner's resources that are visible to
 *   everyone, to their role (parents/children), or to them specifically.
 *
 * @method static Builder visibleTo(User $user)
 */
trait HasFamilyVisibility
{
    /** @param Builder<static> $query */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $member = $user->linkedFamilyMember();

        return $query->where(function (Builder $q) use ($user, $member) {
            $q->where('user_id', $user->id);

            if (! $member) {
                return;
            }

            $q->orWhere(function (Builder $owned) use ($member) {
                $owned->where('user_id', $member->user_id)
                    ->where(function (Builder $vis) use ($member) {
                        $vis->where('visibility', ListVisibility::Everyone->value)
                            ->when($member->role === FamilyRole::Parent, fn ($q) => $q->orWhere('visibility', ListVisibility::Parents->value))
                            ->when($member->role === FamilyRole::Child, fn ($q) => $q->orWhere('visibility', ListVisibility::Children->value))
                            ->orWhere(fn ($specific) => $specific
                                ->where('visibility', ListVisibility::Specific->value)
                                ->whereHas('members', fn ($m) => $m->where('family_member_id', $member->id)));
                    });
            });
        });
    }

    public function isVisibleTo(User $user): bool
    {
        if ($this->user_id === $user->id) {
            return true;
        }

        $member = FamilyMember::where('linked_user_id', $user->id)
            ->where('user_id', $this->user_id)
            ->first();

        if (! $member) {
            return false;
        }

        return match ($this->visibility) {
            ListVisibility::Everyone => true,
            ListVisibility::Parents => $member->role === FamilyRole::Parent,
            ListVisibility::Children => $member->role === FamilyRole::Child,
            ListVisibility::Specific => $this->members()->where('family_member_id', $member->id)->exists(),
        };
    }
}
