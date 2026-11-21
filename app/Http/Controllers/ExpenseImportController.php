<?php

namespace App\Http\Controllers;

use App\Services\ExpenseCsvImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ExpenseImportController extends Controller
{
    public function __invoke(Request $request, ExpenseCsvImporter $importer): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:1024', 'mimes:csv,txt']]);

        try {
            $result = $importer->import(
                $request->user()->familyOwner(),
                (string) file_get_contents($request->file('file')->getRealPath()),
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($result);
    }
}
