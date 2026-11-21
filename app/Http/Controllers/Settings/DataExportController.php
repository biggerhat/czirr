<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\BonusObjective;
use App\Models\BudgetCategory;
use App\Models\Chore;
use App\Models\ChoreCompletion;
use App\Models\Contact;
use App\Models\Cookbook;
use App\Models\Event;
use App\Models\Expense;
use App\Models\FamilyList;
use App\Models\FamilyMember;
use App\Models\Income;
use App\Models\MealPlanEntry;
use App\Models\Recipe;
use App\Models\Reward;
use App\Models\RewardRedemption;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Download everything the family has stored as one JSON file. Only the family
 * owner can do this, and no credentials, tokens or push keys are included.
 */
class DataExportController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user->familyOwnerId() === $user->id, 403, 'Only the family owner can export family data.');

        $id = $user->id;

        $data = [
            'exported_at' => now()->toIso8601String(),
            'app' => config('app.name'),
            'family_members' => FamilyMember::where('user_id', $id)->get()->map->only(['id', 'name', 'nickname', 'role', 'color']),
            'chores' => Chore::where('user_id', $id)->with('assignments')->get(),
            'chore_completions' => ChoreCompletion::withoutGlobalScope('approved')
                ->whereHas('assignment.chore', fn ($q) => $q->where('user_id', $id))->get(),
            'bonus_objectives' => BonusObjective::where('user_id', $id)->get(),
            'rewards' => Reward::where('user_id', $id)->get(),
            'reward_redemptions' => RewardRedemption::where('user_id', $id)->get(),
            'events' => Event::where('user_id', $id)->get(),
            'recipes' => Recipe::where('user_id', $id)->with(['cuisine:id,name', 'tags:id,name'])->get(),
            'cookbooks' => Cookbook::where('user_id', $id)->with('recipes:id,name')->get(),
            'meal_plan' => MealPlanEntry::where('user_id', $id)->get(),
            'lists' => FamilyList::where('user_id', $id)->with('items')->get(),
            'contacts' => Contact::where('user_id', $id)->get(),
            'grid_games' => \App\Models\GridGame::where('user_id', $id)->get(),
            'score_games' => \App\Models\ScoreGame::where('user_id', $id)->get(),
            'savings' => \App\Models\SavingsEntry::where('user_id', $id)->get(),
            'budget' => [
                'categories' => BudgetCategory::where('user_id', $id)->get(),
                'bills' => Bill::where('user_id', $id)->get(),
                'incomes' => Income::where('user_id', $id)->get(),
                'expenses' => Expense::where('user_id', $id)->get(),
            ],
        ];

        $filename = 'family-export-'.now()->format('Y-m-d').'.json';

        return response()->streamDownload(
            fn () => print json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $filename,
            ['Content-Type' => 'application/json'],
        );
    }
}
