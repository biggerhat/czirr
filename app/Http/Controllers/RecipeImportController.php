<?php

namespace App\Http\Controllers;

use App\Services\RecipeImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class RecipeImportController extends Controller
{
    public function __invoke(Request $request, RecipeImporter $importer): JsonResponse
    {
        $data = $request->validate(['url' => ['required', 'string', 'max:2048']]);

        try {
            return response()->json($importer->import($data['url']));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Illuminate\Http\Client\ConnectionException) {
            return response()->json(['message' => 'Could not reach that site.'], 422);
        }
    }
}
