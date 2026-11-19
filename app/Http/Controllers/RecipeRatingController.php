<?php

namespace App\Http\Controllers;

use App\Models\Recipe;
use App\Models\RecipeRating;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecipeRatingController extends Controller
{
    public function update(Request $request, Recipe $recipe): JsonResponse
    {
        abort_unless($recipe->user_id === $request->user()->familyOwnerId(), 403);

        $data = $request->validate(['rating' => ['required', 'integer', 'between:1,5']]);

        RecipeRating::updateOrCreate(
            ['recipe_id' => $recipe->id, 'user_id' => $request->user()->id],
            ['rating' => $data['rating']],
        );

        return response()->json(self::summary($recipe, $request->user()->id));
    }

    public function destroy(Request $request, Recipe $recipe): JsonResponse
    {
        abort_unless($recipe->user_id === $request->user()->familyOwnerId(), 403);

        RecipeRating::where('recipe_id', $recipe->id)->where('user_id', $request->user()->id)->delete();

        return response()->json(self::summary($recipe, $request->user()->id));
    }

    /** @return array{mine: int|null, average: float|null, count: int} */
    public static function summary(Recipe $recipe, int $userId): array
    {
        $ratings = RecipeRating::where('recipe_id', $recipe->id)->get(['user_id', 'rating']);

        return [
            'mine' => $ratings->firstWhere('user_id', $userId)?->rating,
            'average' => $ratings->isEmpty() ? null : round($ratings->avg('rating'), 1),
            'count' => $ratings->count(),
        ];
    }
}
