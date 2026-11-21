<?php

namespace App\Http\Controllers;

use App\Enums\ListType;
use App\Enums\ListVisibility;
use App\Enums\MealType;
use App\Models\FamilyList;
use App\Models\MealPlanEntry;
use App\Models\PantryItem;
use App\Models\Recipe;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MealPlanController extends Controller
{
    public function index(Request $request): Response
    {
        $startDate = $request->query('start')
            ? Carbon::parse($request->query('start'))->startOfDay()
            : $request->user()->localNow()->startOfWeek(Carbon::MONDAY);

        $endDate = $startDate->copy()->addDays(13)->endOfDay();

        $entries = $this->getVisibleEntries($request)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->with('recipe:id,name,description,prep_time,cook_time,servings,difficulty')
            ->orderBy('date')
            ->orderByMealType()
            ->get();

        $recipes = $this->getVisibleRecipes($request)
            ->orderBy('name')
            ->get(['id', 'name']);

        $customMeals = $this->getVisibleEntries($request)
            ->whereNull('recipe_id')
            ->select('name', 'description')
            ->distinct()
            ->orderBy('name')
            ->get();

        $user = $request->user();

        return Inertia::render('meal-plans/Index', [
            'entries' => $entries,
            'recipes' => $recipes,
            'customMeals' => $customMeals,
            'startDate' => $startDate->toDateString(),
            'endDate' => $endDate->toDateString(),
            'can' => [
                'create' => $user->can('meal-plans.create'),
                'edit' => $user->can('meal-plans.edit'),
                'delete' => $user->can('meal-plans.delete'),
                'generateGroceryList' => $user->can('meal-plans.generate-grocery-list'),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $ownerId = $request->user()->familyOwnerId();

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'meal_type' => ['required', 'string', Rule::in(array_column(MealType::cases(), 'value'))],
            'recipe_id' => ['nullable', 'integer', Rule::exists('recipes', 'id')->where('user_id', $ownerId)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $owner = $request->user()->familyOwner();
        $entry = $owner->mealPlanEntries()->create($validated);
        $entry->load('recipe:id,name');

        return response()->json($entry, 201);
    }

    public function update(Request $request, MealPlanEntry $mealPlanEntry): JsonResponse
    {
        if ($mealPlanEntry->user_id !== $request->user()->familyOwnerId()) {
            abort(403);
        }

        $ownerId = $request->user()->familyOwnerId();

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'meal_type' => ['required', 'string', Rule::in(array_column(MealType::cases(), 'value'))],
            'recipe_id' => ['nullable', 'integer', Rule::exists('recipes', 'id')->where('user_id', $ownerId)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $mealPlanEntry->update($validated);
        $mealPlanEntry->load('recipe:id,name');

        return response()->json($mealPlanEntry);
    }

    public function destroy(Request $request, MealPlanEntry $mealPlanEntry): JsonResponse
    {
        if ($mealPlanEntry->user_id !== $request->user()->familyOwnerId()) {
            abort(403);
        }

        $mealPlanEntry->delete();

        return response()->json(null, 204);
    }

    /**
     * Copy the 7 days before $start into the 7 days starting at $start.
     * Entries already present (same date, meal type and name) are skipped.
     */
    public function copyPreviousWeek(Request $request): JsonResponse
    {
        $validated = $request->validate(['start' => ['required', 'date']]);

        $target = Carbon::parse($validated['start'])->startOfDay();
        $source = $target->copy()->subDays(7);
        $owner = $request->user()->familyOwner();

        $existing = $this->getVisibleEntries($request)
            ->whereBetween('date', [$target->toDateString(), $target->copy()->addDays(6)->toDateString()])
            ->get()
            ->map(fn ($e) => $e->date->toDateString().'|'.$e->meal_type->value.'|'.mb_strtolower($e->name))
            ->flip();

        $copied = 0;
        $this->getVisibleEntries($request)
            ->whereBetween('date', [$source->toDateString(), $source->copy()->addDays(6)->toDateString()])
            ->get()
            ->each(function (MealPlanEntry $entry) use ($owner, $existing, &$copied) {
                $date = $entry->date->copy()->addDays(7);
                $key = $date->toDateString().'|'.$entry->meal_type->value.'|'.mb_strtolower($entry->name);
                if ($existing->has($key)) {
                    return;
                }
                $owner->mealPlanEntries()->create([
                    'date' => $date->toDateString(),
                    'meal_type' => $entry->meal_type,
                    'recipe_id' => $entry->recipe_id,
                    'name' => $entry->name,
                    'description' => $entry->description,
                ]);
                $copied++;
            });

        return response()->json(['copied' => $copied]);
    }

    /**
     * Dinner ideas: recipes not planned in the last 14 days, best-rated first,
     * then whichever the family hasn't made for longest.
     */
    public function suggestions(Request $request): JsonResponse
    {
        $ownerId = $request->user()->familyOwnerId();
        $cutoff = now()->subDays(14)->toDateString();

        $lastPlanned = MealPlanEntry::where('user_id', $ownerId)
            ->whereNotNull('recipe_id')
            ->selectRaw('recipe_id, MAX(date) as last_date')
            ->groupBy('recipe_id')
            ->pluck('last_date', 'recipe_id');

        $ratings = \App\Models\RecipeRating::selectRaw('recipe_id, AVG(rating) as avg')
            ->groupBy('recipe_id')
            ->pluck('avg', 'recipe_id');

        $ideas = $this->getVisibleRecipes($request)
            ->get(['id', 'name', 'prep_time', 'cook_time'])
            ->reject(fn ($r) => ($lastPlanned[$r->id] ?? '0000') >= $cutoff)
            ->sortBy([
                fn ($a, $b) => ($ratings[$b->id] ?? 0) <=> ($ratings[$a->id] ?? 0),
                fn ($a, $b) => ($lastPlanned[$a->id] ?? '0000') <=> ($lastPlanned[$b->id] ?? '0000'),
            ])
            ->take(8)
            ->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'total_time' => ($r->prep_time ?? 0) + ($r->cook_time ?? 0),
                'rating' => isset($ratings[$r->id]) ? round($ratings[$r->id], 1) : null,
                'last_planned' => $lastPlanned[$r->id] ?? null,
            ])
            ->values();

        return response()->json($ideas);
    }

    public function generateGroceryList(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after_or_equal:start'],
            'mode' => ['sometimes', 'string', 'in:create,replace'],
        ]);

        $mode = $validated['mode'] ?? 'create';

        $startDate = Carbon::parse($validated['start'])->startOfDay();
        $endDate = Carbon::parse($validated['end'])->endOfDay();

        $entries = $this->getVisibleEntries($request)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->whereNotNull('recipe_id')
            ->with('recipe:id,ingredients')
            ->get();

        $allIngredients = [];
        foreach ($entries as $entry) {
            $ingredients = $entry->recipe->ingredients ?? [];
            foreach ($ingredients as $ingredient) {
                if (! empty($ingredient['name'])) {
                    $allIngredients[] = $ingredient;
                }
            }
        }

        if (empty($allIngredients)) {
            return response()->json([
                'message' => 'No recipe ingredients found in this period.',
            ], 422);
        }

        // Skip staples the family always has at home (exact match after normalising).
        $staples = PantryItem::where('user_id', $request->user()->familyOwnerId())->pluck('name_key')->flip();
        $skipped = [];
        $allIngredients = array_values(array_filter($allIngredients, function ($ingredient) use ($staples, &$skipped) {
            if ($staples->has(PantryItem::normalize($ingredient['name']))) {
                $skipped[mb_strtolower(trim($ingredient['name']))] = trim($ingredient['name']);

                return false;
            }

            return true;
        }));

        if (empty($allIngredients)) {
            return response()->json([
                'message' => 'Everything you need is already in your pantry — nothing to buy.',
            ], 422);
        }

        if ($skipped) {
            session()->flash('status', 'Left off '.count($skipped).' pantry staple'.(count($skipped) === 1 ? '' : 's').': '.implode(', ', array_values($skipped)).'.');
        }

        $merged = $this->mergeIngredients($allIngredients);

        $listName = $this->groceryListName($startDate, $endDate);

        $ownerId = $request->user()->familyOwnerId();

        // Check for an existing grocery list with the same name
        $existing = FamilyList::where('user_id', $ownerId)
            ->where('name', $listName)
            ->where('type', ListType::Grocery)
            ->first();

        if ($existing && $mode === 'create') {
            return response()->json([
                'conflict' => true,
                'existing_id' => $existing->id,
                'message' => 'A grocery list for this period already exists.',
            ], 409);
        }

        if ($existing && $mode === 'replace') {
            $existing->items()->delete();
            $existing->items()->createMany($merged);

            return response()->json(['id' => $existing->id], 200);
        }

        $list = new FamilyList([
            'name' => $listName,
            'type' => ListType::Grocery,
            'visibility' => ListVisibility::Everyone,
        ]);
        $list->user_id = $ownerId;
        $list->save();

        $list->items()->createMany($merged);

        return response()->json(['id' => $list->id], 201);
    }

    private function groceryListName(Carbon $start, Carbon $end): string
    {
        $sMonth = $start->format('M');
        $eMonth = $end->format('M');
        $sDay = $start->day;
        $eDay = $end->day;
        $dateRange = $sMonth === $eMonth
            ? "{$sMonth} {$sDay}–{$eDay}"
            : "{$sMonth} {$sDay}–{$eMonth} {$eDay}";

        return "Groceries – {$dateRange}";
    }

    /**
     * Merge duplicate ingredients by name, aggregating quantities.
     */
    private function mergeIngredients(array $ingredients): array
    {
        $groups = [];

        foreach ($ingredients as $ingredient) {
            $key = mb_strtolower(trim($ingredient['name']));
            $groups[$key][] = $ingredient;
        }

        $result = [];
        $position = 0;

        foreach ($groups as $items) {
            $name = trim($items[0]['name']);
            $notes = [];
            $quantities = [];

            foreach ($items as $item) {
                $qty = isset($item['quantity']) ? trim((string) $item['quantity']) : '';
                $unit = isset($item['unit']) ? trim($item['unit']) : '';

                if ($qty !== '' || $unit !== '') {
                    $quantities[] = ['qty' => $qty, 'unit' => $unit];
                }

                if (! empty($item['notes'])) {
                    $notes[] = trim($item['notes']);
                }
            }

            $quantityStr = '';
            if (! empty($quantities)) {
                // Check if all share the same unit and have numeric quantities
                $units = array_unique(array_column($quantities, 'unit'));
                $allNumeric = count($quantities) > 0;
                foreach ($quantities as $q) {
                    if (! is_numeric($q['qty'])) {
                        $allNumeric = false;
                        break;
                    }
                }

                if (count($units) === 1 && $allNumeric) {
                    $total = array_sum(array_column($quantities, 'qty'));
                    // Format without trailing zeros for whole numbers
                    $formatted = fmod($total, 1) == 0 ? (int) $total : $total;
                    $unit = $units[0];
                    $quantityStr = $unit !== '' ? "{$formatted} {$unit}" : (string) $formatted;
                } else {
                    $parts = [];
                    foreach ($quantities as $q) {
                        $part = $q['qty'];
                        if ($q['unit'] !== '') {
                            $part .= ($part !== '' ? ' ' : '').$q['unit'];
                        }
                        if ($part !== '') {
                            $parts[] = $part;
                        }
                    }
                    $quantityStr = implode(', ', $parts);
                }
            }

            $result[] = [
                'name' => $name,
                'quantity' => $quantityStr !== '' ? $quantityStr : null,
                'notes' => ! empty($notes) ? implode('; ', array_unique($notes)) : null,
                'position' => $position++,
                'is_completed' => false,
            ];
        }

        return $result;
    }

    private function getVisibleEntries(Request $request)
    {
        $user = $request->user();
        $ownerId = $user->familyOwnerId();

        return MealPlanEntry::where('user_id', $ownerId);
    }

    private function getVisibleRecipes(Request $request)
    {
        $user = $request->user();
        $ownerId = $user->familyOwnerId();

        return Recipe::where('user_id', $ownerId);
    }
}
