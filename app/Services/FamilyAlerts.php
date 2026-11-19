<?php

namespace App\Services;

use App\Models\FamilyMember;
use App\Models\User;
use App\Notifications\PushAlert;
use Illuminate\Support\Collection;

class FamilyAlerts
{
    /**
     * Users in this family who can manage chores (parents/admins).
     *
     * @return Collection<int, User>
     */
    public function managers(int $ownerId, ?int $exceptUserId = null): Collection
    {
        $userIds = FamilyMember::where('user_id', $ownerId)
            ->whereNotNull('linked_user_id')
            ->pluck('linked_user_id')
            ->push($ownerId)
            ->unique()
            ->reject(fn ($id) => $id === $exceptUserId);

        return User::whereIn('id', $userIds)->get()
            ->filter(fn (User $u) => $u->can('chores.edit'))
            ->values();
    }

    public function notifyManagers(int $ownerId, ?int $exceptUserId, PushAlert $alert): void
    {
        $this->managers($ownerId, $exceptUserId)->each(fn (User $u) => $u->notify($alert));
    }
}
