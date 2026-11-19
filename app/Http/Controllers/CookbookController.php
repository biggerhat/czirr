<?php

namespace App\Http\Controllers;

use App\Enums\ListVisibility;
use App\Models\Cookbook;
use App\Models\FamilyMember;
use App\Models\Recipe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CookbookController extends Controller
{
    public function index(Request $request): Response
    {
        $cookbooks = $this->getVisibleCookbooks($request)
            ->withCount('recipes')
            ->orderBy('updated_at', 'desc')
            ->get();

        $user = $request->user();
        $familyMembers = $this->getFamilyMembers($request);

        return Inertia::render('recipes/cookbooks/Index', [
            'cookbooks' => $cookbooks,
            'familyMembers' => $familyMembers,
            'can' => [
                'create' => $user->can('cookbooks.create'),
                'edit' => $user->can('cookbooks.edit'),
                'delete' => $user->can('cookbooks.delete'),
            ],
        ]);
    }

    public function show(Request $request, Cookbook $cookbook): Response
    {
        $this->authorizeCookbookAccess($request, $cookbook);

        $cookbook->load([
            'recipes' => fn ($q) => $q->orderBy('cookbook_recipe.position'),
            'members',
        ]);

        $user = $request->user();
        $familyMembers = $this->getFamilyMembers($request);

        // Get all recipes for the "add recipe" picker (owner's recipes)
        $allRecipes = $this->getAllRecipes($request);

        return Inertia::render('recipes/cookbooks/Show', [
            'cookbook' => $cookbook,
            'allRecipes' => $allRecipes,
            'familyMembers' => $familyMembers,
            'can' => [
                'edit' => $user->can('cookbooks.edit'),
                'delete' => $user->can('cookbooks.delete'),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'visibility' => ['required', 'string', Rule::in(array_column(ListVisibility::cases(), 'value'))],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['integer', 'exists:family_members,id'],
        ]);

        $owner = $request->user()->familyOwner();

        /** @var Cookbook $cookbook */
        $cookbook = $owner->cookbooks()->create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'visibility' => $validated['visibility'],
        ]);

        if ($validated['visibility'] === ListVisibility::Specific->value && ! empty($validated['member_ids'])) {
            $cookbook->members()->sync($validated['member_ids']);
        }

        return response()->json($cookbook, 201);
    }

    public function update(Request $request, Cookbook $cookbook): JsonResponse
    {
        if ($cookbook->user_id !== $request->user()->familyOwnerId()) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'visibility' => ['required', 'string', Rule::in(array_column(ListVisibility::cases(), 'value'))],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['integer', 'exists:family_members,id'],
        ]);

        $cookbook->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'visibility' => $validated['visibility'],
        ]);

        if ($validated['visibility'] === ListVisibility::Specific->value) {
            $cookbook->members()->sync($validated['member_ids'] ?? []);
        } else {
            $cookbook->members()->detach();
        }

        return response()->json($cookbook);
    }

    public function destroy(Request $request, Cookbook $cookbook): JsonResponse
    {
        if ($cookbook->user_id !== $request->user()->familyOwnerId()) {
            abort(403);
        }

        $cookbook->delete();

        return response()->json(null, 204);
    }

    public function addRecipe(Request $request, Cookbook $cookbook): JsonResponse
    {
        $this->authorizeCookbookAccess($request, $cookbook);

        $ownerId = $request->user()->familyOwnerId();

        $validated = $request->validate([
            'recipe_id' => ['required', 'integer', Rule::exists('recipes', 'id')->where('user_id', $ownerId)],
        ]);

        $maxPosition = $cookbook->recipes()->max('cookbook_recipe.position') ?? -1;

        $cookbook->recipes()->syncWithoutDetaching([
            $validated['recipe_id'] => ['position' => $maxPosition + 1],
        ]);

        return response()->json(null, 201);
    }

    public function removeRecipe(Request $request, Cookbook $cookbook, Recipe $recipe): JsonResponse
    {
        $this->authorizeCookbookAccess($request, $cookbook);

        $cookbook->recipes()->detach($recipe->id);

        return response()->json(null, 204);
    }

    private function getVisibleCookbooks(Request $request)
    {
        return Cookbook::visibleTo($request->user());
    }

    private function authorizeCookbookAccess(Request $request, Cookbook $cookbook): void
    {
        abort_unless($cookbook->isVisibleTo($request->user()), 403);
    }

    private function getFamilyMembers(Request $request)
    {
        $user = $request->user();
        $ownerId = $user->familyOwnerId();

        return FamilyMember::where('user_id', $ownerId)->orderBy('name')->get();
    }

    private function getAllRecipes(Request $request)
    {
        $user = $request->user();
        $ownerId = $user->familyOwnerId();

        return Recipe::where('user_id', $ownerId)->orderBy('name')->get();
    }
}
