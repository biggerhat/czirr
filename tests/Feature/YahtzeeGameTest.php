<?php

use App\Models\User;
use App\Models\YahtzeeGame;
use App\Services\YahtzeeScoreService;

function startGame(User $a, User $b): YahtzeeGame
{
    $id = test()->actingAs($a)->postJson(route('yahtzee.store'), ['opponent_id' => $b->id])
        ->assertCreated()->json('id');

    return YahtzeeGame::findOrFail($id);
}

function setDice(YahtzeeGame $game, array $values, int $rollsLeft = 2): void
{
    $game->update([
        'dice' => array_map(fn ($v) => ['value' => $v, 'held' => false], $values),
        'rolls_left' => $rollsLeft,
    ]);
}

test('a game can only be started against a family member', function () {
    $parent = createAdminUser();
    $stranger = createAdminUser();

    $this->actingAs($parent)->postJson(route('yahtzee.store'), ['opponent_id' => $stranger->id])
        ->assertStatus(422);
});

test('turns are enforced and rolling uses up rolls', function () {
    $kid = createUserWithRole('child');
    $parent = User::findOrFail($kid->linkedFamilyMember()->user_id);
    $game = startGame($parent, $kid);

    $this->actingAs($kid)->postJson(route('yahtzee.roll', $game))->assertForbidden();

    $this->actingAs($parent)->postJson(route('yahtzee.roll', $game))->assertNoContent();
    expect($game->fresh()->rolls_left)->toBe(2);

    $game->update(['rolls_left' => 0]);
    $this->actingAs($parent)->postJson(route('yahtzee.roll', $game))->assertStatus(422);
});

test('cannot hold or score before the first roll', function () {
    $kid = createUserWithRole('child');
    $parent = User::findOrFail($kid->linkedFamilyMember()->user_id);
    $game = startGame($parent, $kid);

    $this->actingAs($parent)->patchJson(route('yahtzee.hold', $game), ['index' => 0])->assertStatus(422);
    $this->actingAs($parent)->postJson(route('yahtzee.score', $game), ['category' => 'chance'])->assertStatus(422);
});

test('scoring fills the category, resets dice and passes the turn', function () {
    $kid = createUserWithRole('child');
    $parent = User::findOrFail($kid->linkedFamilyMember()->user_id);
    $game = startGame($parent, $kid);
    setDice($game, [2, 2, 3, 3, 3]);

    $this->actingAs($parent)->postJson(route('yahtzee.score', $game), ['category' => 'full_house'])->assertNoContent();

    $game->refresh();
    expect($game->scorecards[$parent->id]['full_house'])->toBe(25)
        ->and($game->current_turn_user_id)->toBe($kid->id)
        ->and($game->rolls_left)->toBe(3);

    // Category can't be reused
    $game->update(['current_turn_user_id' => $parent->id]);
    setDice($game, [1, 1, 1, 1, 1]);
    $this->actingAs($parent)->postJson(route('yahtzee.score', $game), ['category' => 'full_house'])->assertStatus(422);
});

test('extra yahtzees earn the 100 point bonus', function () {
    $kid = createUserWithRole('child');
    $parent = User::findOrFail($kid->linkedFamilyMember()->user_id);
    $game = startGame($parent, $kid);

    $cards = $game->scorecards;
    $cards[$parent->id]['yahtzee'] = 50;
    $game->update(['scorecards' => $cards]);
    setDice($game, [6, 6, 6, 6, 6]);

    $this->actingAs($parent)->postJson(route('yahtzee.score', $game), ['category' => 'sixes'])->assertNoContent();

    expect($game->fresh()->scorecards[$parent->id])->toMatchArray(['sixes' => 30, 'yahtzee_bonus' => 100]);
});

test('the game completes with a winner once both cards are full', function () {
    $kid = createUserWithRole('child');
    $parent = User::findOrFail($kid->linkedFamilyMember()->user_id);
    $game = startGame($parent, $kid);

    $full = array_fill_keys(YahtzeeScoreService::ALL_CATEGORIES, 0) + ['yahtzee_bonus' => 0];
    $parentCard = array_merge($full, ['chance' => null, 'ones' => 5]);
    $kidCard = array_merge($full, ['ones' => 1]);
    $game->update(['scorecards' => [$parent->id => $parentCard, $kid->id => $kidCard]]);
    setDice($game, [6, 6, 1, 2, 3]);

    $this->actingAs($parent)->postJson(route('yahtzee.score', $game), ['category' => 'chance'])->assertNoContent();

    $game->refresh();
    expect($game->status)->toBe('completed')->and($game->winner_id)->toBe($parent->id);

    $this->actingAs($parent)->postJson(route('yahtzee.roll', $game))->assertStatus(422);
});

test('outsiders cannot see or delete a game', function () {
    $kid = createUserWithRole('child');
    $parent = User::findOrFail($kid->linkedFamilyMember()->user_id);
    $game = startGame($parent, $kid);
    $outsider = createAdminUser();

    $this->actingAs($outsider)->get(route('yahtzee.show', $game))->assertForbidden();
    $this->actingAs($outsider)->deleteJson(route('yahtzee.destroy', $game))->assertForbidden();
    $this->actingAs($kid)->deleteJson(route('yahtzee.destroy', $game))->assertNoContent();
});
