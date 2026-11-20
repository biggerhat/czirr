<?php

use App\Models\FamilyMember;
use App\Models\GridGame;
use App\Models\User;
use App\Services\GridGameRules;

function duelPair(): array
{
    $parent = createAdminUser();
    $kid = User::factory()->create();
    $kid->assignRole('child');
    FamilyMember::factory()->create(['user_id' => $parent->id, 'name' => 'Kid', 'linked_user_id' => $kid->id]);

    return [$parent, $kid];
}

test('rules detect wins in every direction and gravity drops discs', function () {
    $b = GridGameRules::emptyBoard('tictactoe');
    foreach ([0, 4, 8] as $i) {
        $b[$i] = 1;
    }
    expect(GridGameRules::winner('tictactoe', $b))->toBe(1);

    $c = GridGameRules::emptyBoard('connect4');
    foreach ([0, 0, 0, 0] as $col) {
        $c = GridGameRules::applyMove('connect4', $c, $col, 2);
    }
    expect(GridGameRules::winner('connect4', $c))->toBe(2)
        ->and(GridGameRules::applyMove('connect4', $c, 0, 1))->not->toBeNull()
        ->and(GridGameRules::applyMove('connect4', $c, 9, 1))->toBeNull();
});

test('players alternate turns and the winner is recorded', function () {
    [$parent, $kid] = duelPair();
    $id = $this->actingAs($parent)->postJson('/games/duels', ['kind' => 'tictactoe', 'opponent_id' => $kid->id])
        ->assertCreated()->json('id');

    $this->actingAs($kid)->postJson("/games/duels/$id/move", ['move' => 0])->assertForbidden();

    foreach ([[$parent, 0], [$kid, 3], [$parent, 1], [$kid, 4]] as [$who, $m]) {
        $this->actingAs($who)->postJson("/games/duels/$id/move", ['move' => $m])->assertNoContent();
    }
    $this->actingAs($parent)->postJson("/games/duels/$id/move", ['move' => 3])->assertStatus(422); // taken
    $this->actingAs($parent)->postJson("/games/duels/$id/move", ['move' => 2])->assertNoContent();

    $g = GridGame::find($id);
    expect($g->status)->toBe('won')->and($g->winner_id)->toBe($parent->id);
    $this->actingAs($kid)->postJson("/games/duels/$id/move", ['move' => 5])->assertStatus(422);
    $this->actingAs($kid)->get("/games/duels/$id")->assertOk();
    $this->actingAs($kid)->get('/games/duels')->assertOk();
    $this->actingAs($parent)->get('/games/memory')->assertOk();
});

test('duels need a family opponent and outsiders are blocked', function () {
    [$parent, $kid] = duelPair();
    $stranger = createAdminUser();
    $this->actingAs($parent)->postJson('/games/duels', ['kind' => 'connect4', 'opponent_id' => $stranger->id])->assertStatus(422);

    $id = $this->actingAs($parent)->postJson('/games/duels', ['kind' => 'connect4', 'opponent_id' => $kid->id])->json('id');
    $this->actingAs($stranger)->get("/games/duels/$id")->assertForbidden();
    $this->actingAs($stranger)->deleteJson("/games/duels/$id")->assertForbidden();
});

test('duel wins count toward the game night leaderboard', function () {
    [$parent, $kid] = duelPair();
    $id = $this->actingAs($parent)->postJson('/games/duels', ['kind' => 'tictactoe', 'opponent_id' => $kid->id])->json('id');
    foreach ([[$parent, 0], [$kid, 3], [$parent, 1], [$kid, 4], [$parent, 2]] as [$who, $m]) {
        $this->actingAs($who)->postJson("/games/duels/$id/move", ['move' => $m]);
    }

    $this->actingAs($parent)->get('/games')->assertInertia(
        fn ($page) => $page->where('leaderboard.0.name', $parent->name)->where('leaderboard.0.wins', 1)
    );
});
