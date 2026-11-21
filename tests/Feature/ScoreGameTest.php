<?php

use App\Models\ScoreGame;
use App\Models\User;

function newGame(User $user, array $over = [])
{
    return test()->actingAs($user)->postJson('/games', array_merge([
        'title' => 'Farkle', 'kind' => 'farkle',
        'players' => [['name' => 'Ann'], ['name' => 'Bob']],
    ], $over));
}

test('a farkle game defaults to 10000 and ends when someone reaches it', function () {
    $user = createAdminUser();
    $id = newGame($user)->assertCreated()->json('id');
    expect(ScoreGame::find($id)->target_score)->toBe(10000);

    $this->postJson("/games/$id/rounds", ['scores' => [9500, 300]])->assertNoContent();
    expect(ScoreGame::find($id)->status)->toBe('active');

    $this->postJson("/games/$id/rounds", ['scores' => [600, 0]])->assertNoContent();
    $game = ScoreGame::find($id);
    expect($game->status)->toBe('completed')->and($game->winner_names)->toBe('Ann');

    $this->postJson("/games/$id/rounds", ['scores' => [1, 1]])->assertStatus(422);
    $this->deleteJson("/games/$id/rounds")->assertNoContent();
    expect(ScoreGame::find($id)->status)->toBe('active');
});

test('lowest-wins scorekeeper crowns the lowest score on finish', function () {
    $user = createAdminUser();
    $id = newGame($user, ['kind' => 'scorekeeper', 'title' => 'Golf', 'low_wins' => true])->json('id');
    $this->postJson("/games/$id/rounds", ['scores' => [12, 5]])->assertNoContent();
    $this->postJson("/games/$id/finish")->assertNoContent();

    expect(ScoreGame::find($id)->winner_names)->toBe('Bob');
    $this->get('/games')->assertOk();
    $this->get("/games/$id")->assertOk();
});

test('rounds need one score per player and games are family scoped', function () {
    $user = createAdminUser();
    $id = newGame($user)->json('id');
    $this->postJson("/games/$id/rounds", ['scores' => [1]])->assertStatus(422);
    newGame($user, ['players' => [['name' => 'Solo']]])->assertStatus(422);

    $other = createAdminUser();
    $this->actingAs($other)->postJson("/games/$id/rounds", ['scores' => [1, 2]])->assertForbidden();
    $this->actingAs($other)->get("/games/$id")->assertForbidden();
});
