<?php

use App\Enums\ListVisibility;
use App\Models\Cookbook;
use App\Models\FamilyList;

test('visibility rules apply to lists and cookbooks alike', function (string $kind) {
    $model = $kind === 'list' ? FamilyList::class : Cookbook::class;
    $kid = createUserWithRole('child');
    $ownerId = $kid->linkedFamilyMember()->user_id;
    $kidMember = $kid->linkedFamilyMember();
    $make = fn (ListVisibility $v) => $model::factory()->create(['user_id' => $ownerId, 'visibility' => $v]);

    $everyone = $make(ListVisibility::Everyone);
    $parents = $make(ListVisibility::Parents);
    $children = $make(ListVisibility::Children);
    $specificIn = $make(ListVisibility::Specific);
    $specificOut = $make(ListVisibility::Specific);
    $specificIn->members()->attach($kidMember->id);
    $mine = $model::factory()->create(['user_id' => $kid->id, 'visibility' => ListVisibility::Parents]);

    $visible = $model::visibleTo($kid)->pluck('id')->all();

    expect($visible)->toContain($everyone->id, $children->id, $specificIn->id, $mine->id)
        ->not->toContain($parents->id)
        ->not->toContain($specificOut->id);

    expect($everyone->isVisibleTo($kid))->toBeTrue()
        ->and($parents->isVisibleTo($kid))->toBeFalse()
        ->and($specificIn->isVisibleTo($kid))->toBeTrue()
        ->and($specificOut->isVisibleTo($kid))->toBeFalse();
})->with(['list', 'cookbook']);

test('family owners and parents see parent-only resources; strangers see nothing', function () {
    $owner = createAdminUser();
    $list = FamilyList::factory()->create(['user_id' => $owner->id, 'visibility' => ListVisibility::Parents]);
    $stranger = createAdminUser();

    expect($list->isVisibleTo($owner))->toBeTrue()
        ->and($list->isVisibleTo($stranger))->toBeFalse()
        ->and(FamilyList::visibleTo($stranger)->whereKey($list->id)->exists())->toBeFalse();
});
