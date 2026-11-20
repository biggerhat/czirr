<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// One private channel per family; members and the owner share it.
Broadcast::channel('family.{familyId}', function ($user, $familyId) {
    return (int) $user->familyOwnerId() === (int) $familyId;
});
