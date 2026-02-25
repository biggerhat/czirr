<?php

namespace App\Http\Controllers;

use App\Models\Cuisine;
use App\Models\FamilyMember;
use App\Models\Recipe;
use App\Models\RecipeTag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RecipeController extends Controller
{
    public function index(Request $request): Response
    {
        $recipes = $this->getVisibleRecipes($request)
            ->with(['cuisine', 'tags'])
            ->withCount('cookbooks')
            ->orderBy('updated_at', 'desc')
            ->get();

        $tags = $this->getAvailableTags($request);
        $user = $request->user();

        return Inertia::render('recipes/Index', [
            'recipes' => $recipes,
            'tags' => $tags,
            'can' => [
                'create' => $user->can('recipes.create'),
                'edit' => $user->can('recipes.edit'),
                'delete' => $user->can('recipes.delete'),
            ],
        ]);
    }

    public function show(Request $request, Recipe $recipe): Response
    {
        $this->authorizeRecipeAccess($request, $recipe);

        $recipe->load(['cookbooks', 'cuisine', 'tags']);

        $user = $request->user();
        $canEdit = $user->can('recipes.edit');
        $availableCookbooks = $canEdit
            ? $user->familyOwner()->cookbooks()->orderBy('name')->get()
            : collect();

        return Inertia::render('recipes/Show', [
            'recipe' => $recipe,
            'cookbooks' => $availableCookbooks,
            'can' => [
                'edit' => $canEdit,
                'delete' => $user->can('recipes.delete'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('recipes/Create', [
            'cuisines' => $this->getAvailableCuisines($request),
            'tags' => $this->getAvailableTags($request),
        ]);
    }

    public function edit(Request $request, Recipe $recipe): Response
    {
        if ($recipe->user_id !== $request->user()->familyOwnerId()) {
            abort(403);
        }

        $recipe->load('tags');

        return Inertia::render('recipes/Edit', [
            'recipe' => $recipe,
            'cuisines' => $this->getAvailableCuisines($request),
            'tags' => $this->getAvailableTags($request),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'ingredients' => ['required', 'array', 'min:1'],
            'ingredients.*.name' => ['required', 'string', 'max:255'],
            'ingredients.*.quantity' => ['nullable', 'string', 'max:100'],
            'ingredients.*.unit' => ['nullable', 'string', 'max:50'],
            'ingredients.*.notes' => ['nullable', 'string', 'max:255'],
            'instructions' => ['required', 'string'],
            'prep_time' => ['required', 'integer', 'min:0'],
            'cook_time' => ['required', 'integer', 'min:0'],
            'servings' => ['required', 'integer', 'min:1'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'source_url' => ['nullable', 'string', 'max:2048'],
            'cuisine_id' => ['nullable', 'integer', 'exists:cuisines,id'],
            'difficulty' => ['required', 'string', Rule::in(array_column(\App\Enums\RecipeDifficulty::cases(), 'value'))],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:recipe_tags,id'],
            'notes' => ['nullable', 'string'],
        ]);

        $tagIds = $validated['tag_ids'] ?? [];
        unset($validated['tag_ids']);

        $owner = $request->user()->familyOwner();

        /** @var Recipe $recipe */
        $recipe = $owner->recipes()->create($validated);
        $recipe->tags()->sync($tagIds);

        return response()->json($recipe->load(['cuisine', 'tags']), 201);
    }

    public function update(Request $request, Recipe $recipe): JsonResponse
    {
        if ($recipe->user_id !== $request->user()->familyOwnerId()) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'ingredients' => ['required', 'array', 'min:1'],
            'ingredients.*.name' => ['required', 'string', 'max:255'],
            'ingredients.*.quantity' => ['nullable', 'string', 'max:100'],
            'ingredients.*.unit' => ['nullable', 'string', 'max:50'],
            'ingredients.*.notes' => ['nullable', 'string', 'max:255'],
            'instructions' => ['required', 'string'],
            'prep_time' => ['required', 'integer', 'min:0'],
            'cook_time' => ['required', 'integer', 'min:0'],
            'servings' => ['required', 'integer', 'min:1'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'source_url' => ['nullable', 'string', 'max:2048'],
            'cuisine_id' => ['nullable', 'integer', 'exists:cuisines,id'],
            'difficulty' => ['required', 'string', Rule::in(array_column(\App\Enums\RecipeDifficulty::cases(), 'value'))],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:recipe_tags,id'],
            'notes' => ['nullable', 'string'],
        ]);

        $tagIds = $validated['tag_ids'] ?? [];
        unset($validated['tag_ids']);

        $recipe->update($validated);
        $recipe->tags()->sync($tagIds);

        return response()->json($recipe->load(['cuisine', 'tags']));
    }

    public function destroy(Request $request, Recipe $recipe): JsonResponse
    {
        if ($recipe->user_id !== $request->user()->familyOwnerId()) {
            abort(403);
        }

        $recipe->delete();

        return response()->json(null, 204);
    }

    private function getVisibleRecipes(Request $request)
    {
        $user = $request->user();
        $ownerId = $user->familyOwnerId();

        return Recipe::where('user_id', $ownerId);
    }

    private function authorizeRecipeAccess(Request $request, Recipe $recipe): void
    {
        $user = $request->user();

        if ($recipe->user_id === $user->id) {
            return;
        }

        $linkedMember = FamilyMember::where('linked_user_id', $user->id)
            ->where('user_id', $recipe->user_id)
            ->first();

        if (! $linkedMember) {
            abort(403);
        }
    }

    private function getAvailableCuisines(Request $request)
    {
        return Cuisine::availableTo($request->user()->id)->orderBy('name')->get();
    }

    private function getAvailableTags(Request $request)
    {
        return RecipeTag::availableTo($request->user()->id)->orderBy('name')->get();
    }
}
