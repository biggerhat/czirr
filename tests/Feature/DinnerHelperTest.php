<?php

use App\Models\MealPlanEntry;
use App\Models\Recipe;
use App\Services\RecipeImporter;
use Illuminate\Support\Facades\Http;

test('previous week can be copied without duplicating existing entries', function () {
    $user = createAdminUser();
    $monday = now()->startOfWeek()->addWeek();
    $recipe = Recipe::factory()->create(['user_id' => $user->id]);

    MealPlanEntry::factory()->create([
        'user_id' => $user->id, 'date' => $monday->copy()->subDays(7)->toDateString(),
        'meal_type' => 'dinner', 'name' => 'Tacos', 'recipe_id' => $recipe->id,
    ]);
    MealPlanEntry::factory()->create([
        'user_id' => $user->id, 'date' => $monday->copy()->subDays(5)->toDateString(),
        'meal_type' => 'dinner', 'name' => 'Pasta', 'recipe_id' => null,
    ]);

    $this->actingAs($user)->postJson(route('meal-plans.copy-week'), ['start' => $monday->toDateString()])
        ->assertOk()->assertJson(['copied' => 2]);

    // Second run is a no-op
    $this->actingAs($user)->postJson(route('meal-plans.copy-week'), ['start' => $monday->toDateString()])
        ->assertJson(['copied' => 0]);

    expect(MealPlanEntry::where('user_id', $user->id)->whereDate('date', $monday->toDateString())->where('name', 'Tacos')->exists())->toBeTrue();
});

test('suggestions skip recently planned recipes and prefer higher rated ones', function () {
    $user = createAdminUser();
    $recent = Recipe::factory()->create(['user_id' => $user->id, 'name' => 'Recent']);
    $good = Recipe::factory()->create(['user_id' => $user->id, 'name' => 'Good']);
    $meh = Recipe::factory()->create(['user_id' => $user->id, 'name' => 'Meh']);

    MealPlanEntry::factory()->create(['user_id' => $user->id, 'recipe_id' => $recent->id, 'date' => now()->subDays(3)->toDateString()]);
    $this->actingAs($user)->putJson(route('recipes.rating.update', $good), ['rating' => 5])->assertOk();
    $this->actingAs($user)->putJson(route('recipes.rating.update', $meh), ['rating' => 2])->assertOk();

    $names = $this->actingAs($user)->getJson(route('meal-plans.suggestions'))->assertOk()->collect()->pluck('name')->all();

    expect($names)->toBe(['Good', 'Meh']);
});

test('ratings are per user and validated', function () {
    $user = createAdminUser();
    $recipe = Recipe::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->putJson(route('recipes.rating.update', $recipe), ['rating' => 6])->assertStatus(422);
    $res = $this->actingAs($user)->putJson(route('recipes.rating.update', $recipe), ['rating' => 4])->assertOk();
    expect($res->json())->toMatchArray(['mine' => 4, 'average' => 4.0, 'count' => 1]);

    $this->actingAs($user)->deleteJson(route('recipes.rating.destroy', $recipe))->assertJson(['count' => 0]);

    $other = Recipe::factory()->create();
    $this->actingAs($user)->putJson(route('recipes.rating.update', $other), ['rating' => 3])->assertForbidden();
});

/** Importer whose DNS answers are scripted, so tests need no network. */
function fakeResolvingImporter(array $dns): RecipeImporter
{
    return new class($dns) extends RecipeImporter
    {
        public function __construct(private array $dns) {}

        protected function resolve(string $host): array
        {
            return $this->dns[$host] ?? [];
        }
    };
}

test('importer parses schema.org recipe json-ld', function () {
    $ld = json_encode(['@context' => 'https://schema.org', '@graph' => [
        ['@type' => 'WebPage'],
        [
            '@type' => 'Recipe', 'name' => 'Pancakes', 'description' => 'Fluffy',
            'recipeIngredient' => ['2 cups flour', '1 tbsp sugar', 'pinch of salt', 'milk'],
            'recipeInstructions' => [['@type' => 'HowToStep', 'text' => 'Mix.'], ['@type' => 'HowToStep', 'text' => 'Cook.']],
            'prepTime' => 'PT15M', 'cookTime' => 'PT1H5M', 'recipeYield' => ['4 servings'],
            'image' => ['https://img.example.com/a.jpg'],
        ],
    ]]);
    Http::fake(['*' => Http::response("<html><script type=\"application/ld+json\">{$ld}</script></html>")]);

    $r = fakeResolvingImporter(['recipes.example.com' => ['93.184.216.34']])->import('https://recipes.example.com/p');

    expect($r['name'])->toBe('Pancakes')
        ->and($r['prep_time'])->toBe(15)->and($r['cook_time'])->toBe(65)->and($r['servings'])->toBe(4)
        ->and($r['ingredients'][0])->toMatchArray(['quantity' => '2', 'unit' => 'cups', 'name' => 'flour'])
        ->and($r['ingredients'][3]['name'])->toBe('milk')
        ->and($r['instructions'])->toBe("Mix.\n\nCook.")
        ->and($r['image_url'])->toBe('https://img.example.com/a.jpg');
});

test('importer refuses internal addresses and odd schemes', function (string $url, array $dns) {
    Http::fake();
    expect(fn () => fakeResolvingImporter($dns)->import($url))->toThrow(InvalidArgumentException::class);
    Http::assertNothingSent();
})->with([
    'loopback ip' => ['http://127.0.0.1/x', []],
    'metadata ip' => ['http://169.254.169.254/latest', []],
    'private via dns' => ['https://evil.example.com/x', ['evil.example.com' => ['10.0.0.5']]],
    'mixed dns answers' => ['https://mixed.example.com/x', ['mixed.example.com' => ['93.184.216.34', '192.168.1.1']]],
    'file scheme' => ['file:///etc/passwd', []],
    'odd port' => ['http://example.com:6379/', ['example.com' => ['93.184.216.34']]],
    'credentials' => ['http://user:pw@example.com/', ['example.com' => ['93.184.216.34']]],
]);

test('import endpoint requires permission and reports failures', function () {
    $user = createAdminUser();

    $this->actingAs($user)->postJson(route('recipes.import'), ['url' => 'http://127.0.0.1/'])
        ->assertStatus(422)->assertJsonStructure(['message']);
});
