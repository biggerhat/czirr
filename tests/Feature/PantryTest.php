<?php

use App\Models\FamilyList;
use App\Models\MealPlanEntry;
use App\Models\PantryItem;
use App\Models\Recipe;

function plannedDinner(array $ingredients): array
{
    $owner = createAdminUser();
    $recipe = Recipe::factory()->create(['user_id' => $owner->id, 'ingredients' => $ingredients]);
    MealPlanEntry::factory()->create([
        'user_id' => $owner->id, 'recipe_id' => $recipe->id, 'date' => '2026-03-10', 'meal_type' => 'dinner', 'name' => $recipe->name,
    ]);

    return [$owner, ['start' => '2026-03-09', 'end' => '2026-03-15']];
}

test('names are normalised so plurals, case and notes match', function () {
    expect(PantryItem::normalize('  Eggs '))->toBe('egg')
        ->and(PantryItem::normalize('Salt (kosher)'))->toBe('salt')
        ->and(PantryItem::normalize('Tomatoes'))->toBe('tomato')
        ->and(PantryItem::normalize('berries'))->toBe('berry')
        ->and(PantryItem::normalize('Glass'))->toBe('glass')
        ->and(PantryItem::normalize('Olive  Oil!'))->toBe('olive oil')
        ->and(PantryItem::normalize('sugar'))->not->toBe(PantryItem::normalize('brown sugar'));
});

test('staples can be added singly or in bulk without duplicates', function () {
    $owner = createAdminUser();

    $this->actingAs($owner)->postJson('/pantry', ['name' => 'Salt'])->assertCreated()->assertJson(['added' => 1]);
    $this->actingAs($owner)->postJson('/pantry', ['names' => ['salt', 'Olive Oil', 'Flour', '  ']])->assertCreated()->assertJson(['added' => 2]);

    expect(PantryItem::where('user_id', $owner->id)->count())->toBe(3);
    $this->actingAs($owner)->get('/pantry')->assertInertia(fn ($p) => $p->has('items', 3)->where('can.edit', true));
});

test('generated grocery lists skip pantry staples and say so', function () {
    [$owner, $range] = plannedDinner([
        ['name' => 'Salt', 'quantity' => '1', 'unit' => 'tsp'],
        ['name' => 'Chicken', 'quantity' => '2', 'unit' => 'lb'],
        ['name' => 'Brown Sugar', 'quantity' => '1', 'unit' => 'cup'],
        ['name' => 'olive oil', 'quantity' => '2', 'unit' => 'tbsp'],
    ]);
    $owner->pantryItems()->createMany([['name' => 'salt'], ['name' => 'Olive Oils'], ['name' => 'Sugar']]);

    $res = $this->actingAs($owner)->postJson('/meal-plans/generate-grocery-list', $range)->assertCreated();

    $names = FamilyList::find($res->json('id'))->items->pluck('name')->all();
    expect($names)->toContain('Chicken', 'Brown Sugar')->not->toContain('Salt')->not->toContain('olive oil');
    expect(session('status'))->toContain('Left off 2 pantry staples')->toContain('Salt');
});

test('a plan made only of staples reports nothing to buy', function () {
    [$owner, $range] = plannedDinner([['name' => 'Salt'], ['name' => 'Pepper']]);
    $owner->pantryItems()->createMany([['name' => 'salt'], ['name' => 'pepper']]);

    $this->actingAs($owner)->postJson('/meal-plans/generate-grocery-list', $range)
        ->assertStatus(422)->assertJsonFragment(['message' => 'Everything you need is already in your pantry — nothing to buy.']);
    expect(FamilyList::count())->toBe(0);
});

test('lists are unchanged when the pantry is empty', function () {
    [$owner, $range] = plannedDinner([['name' => 'Salt'], ['name' => 'Chicken']]);

    $res = $this->actingAs($owner)->postJson('/meal-plans/generate-grocery-list', $range)->assertCreated();

    expect(FamilyList::find($res->json('id'))->items)->toHaveCount(2)->and(session('status'))->toBeNull();
});

test('pantries are private to each family and kids cannot edit', function () {
    $owner = createAdminUser();
    $other = createAdminUser();
    $item = $owner->pantryItems()->create(['name' => 'Salt']);
    $kid = createUserWithRole('child');

    $this->actingAs($other)->deleteJson("/pantry/{$item->id}")->assertForbidden();
    $this->actingAs($kid)->postJson('/pantry', ['name' => 'Candy'])->assertForbidden();
    $this->actingAs($other)->get('/pantry')->assertInertia(fn ($p) => $p->has('items', 0));
    $this->actingAs($owner)->deleteJson("/pantry/{$item->id}")->assertNoContent();
});
