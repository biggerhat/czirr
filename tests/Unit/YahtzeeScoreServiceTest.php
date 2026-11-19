<?php

use App\Services\YahtzeeScoreService;

beforeEach(fn () => $this->svc = new YahtzeeScoreService);

test('upper section counts only matching faces', function () {
    expect($this->svc->scoreFor('threes', [3, 3, 1, 3, 6]))->toBe(9)
        ->and($this->svc->scoreFor('sixes', [1, 2, 3, 4, 5]))->toBe(0)
        ->and($this->svc->scoreFor('ones', [1, 1, 1, 1, 1]))->toBe(5);
});

test('of-a-kind scores sum of all dice only when satisfied', function () {
    expect($this->svc->scoreFor('three_of_a_kind', [2, 2, 2, 5, 6]))->toBe(17)
        ->and($this->svc->scoreFor('three_of_a_kind', [2, 2, 3, 5, 6]))->toBe(0)
        ->and($this->svc->scoreFor('four_of_a_kind', [4, 4, 4, 4, 1]))->toBe(17)
        ->and($this->svc->scoreFor('four_of_a_kind', [4, 4, 4, 1, 1]))->toBe(0)
        ->and($this->svc->scoreFor('three_of_a_kind', [5, 5, 5, 5, 5]))->toBe(25);
});

test('full house requires a pair and a triple', function () {
    expect($this->svc->scoreFor('full_house', [2, 2, 3, 3, 3]))->toBe(25)
        ->and($this->svc->scoreFor('full_house', [2, 2, 3, 3, 4]))->toBe(0)
        ->and($this->svc->scoreFor('full_house', [3, 3, 3, 3, 2]))->toBe(0);
});

test('straights', function () {
    expect($this->svc->scoreFor('small_straight', [1, 2, 3, 4, 6]))->toBe(30)
        ->and($this->svc->scoreFor('small_straight', [3, 4, 5, 6, 6]))->toBe(30)
        ->and($this->svc->scoreFor('small_straight', [1, 2, 3, 5, 6]))->toBe(0)
        ->and($this->svc->scoreFor('large_straight', [2, 3, 4, 5, 6]))->toBe(40)
        ->and($this->svc->scoreFor('large_straight', [1, 2, 3, 4, 6]))->toBe(0);
});

test('yahtzee and chance', function () {
    expect($this->svc->scoreFor('yahtzee', [4, 4, 4, 4, 4]))->toBe(50)
        ->and($this->svc->scoreFor('yahtzee', [4, 4, 4, 4, 3]))->toBe(0)
        ->and($this->svc->scoreFor('chance', [1, 2, 3, 4, 6]))->toBe(16)
        ->and($this->svc->scoreFor('nonsense', [1, 2, 3, 4, 6]))->toBe(0);
});

test('upper bonus triggers at 63 and grand total adds everything', function () {
    $card = $this->svc->emptyScorecard();
    foreach (['ones' => 3, 'twos' => 6, 'threes' => 9, 'fours' => 12, 'fives' => 15, 'sixes' => 18] as $k => $v) {
        $card[$k] = $v;
    }
    expect($this->svc->upperTotal($card))->toBe(63)->and($this->svc->upperBonus($card))->toBe(35);

    $card['chance'] = 20;
    $card['yahtzee'] = 50;
    $card['yahtzee_bonus'] = 100;
    expect($this->svc->grandTotal($card))->toBe(63 + 35 + 70 + 100);

    $card['sixes'] = 12; // 57 — below threshold
    expect($this->svc->upperBonus($card))->toBe(0);
});

test('preview lists only open categories and completion needs all thirteen', function () {
    $card = $this->svc->emptyScorecard();
    $card['chance'] = 10;

    $preview = $this->svc->previewAll([1, 1, 1, 1, 1], $card);
    expect($preview)->not->toHaveKey('chance')->and($preview['yahtzee'])->toBe(50);

    expect($this->svc->isComplete($card))->toBeFalse();
    foreach (YahtzeeScoreService::ALL_CATEGORIES as $c) {
        $card[$c] = 0;
    }
    expect($this->svc->isComplete($card))->toBeTrue();
});
