<?php

namespace App\Support;

use App\Events\FamilyDataChanged;
use App\Models\Bill;
use App\Models\BoardPost;
use App\Models\Chore;
use App\Models\ChoreAssignment;
use App\Models\ChoreCompletion;
use App\Models\Contact;
use App\Models\Cookbook;
use App\Models\Event;
use App\Models\Expense;
use App\Models\FamilyList;
use App\Models\FamilyListItem;
use App\Models\FamilyMember;
use App\Models\GridGame;
use App\Models\Income;
use App\Models\MealPlanEntry;
use App\Models\PantryItem;
use App\Models\Recipe;
use App\Models\Reward;
use App\Models\RewardRedemption;
use App\Models\SavingsEntry;
use App\Models\ScoreGame;
use App\Models\YahtzeeGame;
use Illuminate\Database\Eloquent\Model;

/**
 * Broadcasts a "data changed" ping whenever a family-owned model is saved or
 * deleted, so every open device refreshes through Inertia without polling.
 */
class FamilyRealtime
{
    /** @var array<class-string<Model>, string> model => resource key (see useRealtime.ts) */
    public const MODELS = [
        FamilyList::class => 'lists', FamilyListItem::class => 'lists',
        Chore::class => 'chores', ChoreAssignment::class => 'chores', ChoreCompletion::class => 'chores',
        FamilyMember::class => 'family',
        Reward::class => 'rewards', RewardRedemption::class => 'rewards',
        BoardPost::class => 'board', PantryItem::class => 'pantry', SavingsEntry::class => 'savings',
        MealPlanEntry::class => 'meals', Event::class => 'calendar',
        Bill::class => 'budget', Income::class => 'budget', Expense::class => 'budget',
        Contact::class => 'contacts', Recipe::class => 'recipes', Cookbook::class => 'recipes',
        ScoreGame::class => 'games', GridGame::class => 'games', YahtzeeGame::class => 'games',
    ];

    public static function register(): void
    {
        foreach (self::MODELS as $model => $resource) {
            $ping = fn (Model $m) => self::ping($m, $resource);
            $model::saved($ping);
            $model::deleted($ping);
        }
    }

    public static function enabled(): bool
    {
        return ! in_array(config('broadcasting.default'), [null, 'null', 'log'], true);
    }

    private static function ping(Model $model, string $resource): void
    {
        if (! self::enabled()) {
            return;
        }

        $familyId = self::familyId($model);
        if ($familyId) {
            FamilyDataChanged::dispatch($familyId, $resource, auth()->id());
        }
    }

    private static function familyId(Model $model): ?int
    {
        $id = match (true) {
            $model instanceof FamilyListItem => FamilyList::whereKey($model->getAttribute('family_list_id'))->value('user_id'),
            $model instanceof ChoreAssignment => Chore::whereKey($model->getAttribute('chore_id'))->value('user_id'),
            $model instanceof ChoreCompletion => FamilyMember::whereKey($model->getAttribute('family_member_id'))->value('user_id'),
            default => $model->getAttribute('user_id'),
        };

        return $id ? (int) $id : null;
    }
}
