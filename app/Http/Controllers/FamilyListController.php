<?php

namespace App\Http\Controllers;

use App\Enums\ListVisibility;
use App\Models\FamilyList;
use App\Models\FamilyMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FamilyListController extends Controller
{
    public function index(Request $request): Response
    {
        $lists = $this->getVisibleLists($request)
            ->withCount('items')
            ->with(['items' => fn ($q) => $q->orderBy('is_completed')->orderBy('name')->limit(5)])
            ->orderBy('is_pinned', 'desc')
            ->orderBy('updated_at', 'desc')
            ->get();

        $familyMembers = $this->getFamilyMembers($request);

        return Inertia::render('lists/Index', [
            'lists' => $lists,
            'familyMembers' => $familyMembers,
        ]);
    }

    public function show(Request $request, FamilyList $familyList): Response
    {
        $this->authorizeListAccess($request, $familyList);

        $familyList->load([
            'items' => fn ($q) => $q->orderBy('is_completed')->orderBy('position'),
            'members',
        ]);

        // Closures are skipped on partial reloads, so the live-sync poll stays cheap.
        return Inertia::render('lists/Show', [
            'list' => $familyList,
            'familyMembers' => fn () => $this->getFamilyMembers($request),
            'suggestions' => fn () => $this->suggestions($familyList),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $ownerId = $request->user()->familyOwnerId();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(array_column(\App\Enums\ListType::cases(), 'value'))],
            'visibility' => ['required', 'string', Rule::in(array_column(ListVisibility::cases(), 'value'))],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['integer', Rule::exists('family_members', 'id')->where('user_id', $ownerId)],
        ]);

        $owner = $request->user()->familyOwner();

        /** @var FamilyList $list */
        $list = $owner->familyLists()->create([
            'name' => $validated['name'],
            'type' => $validated['type'],
            'visibility' => $validated['visibility'],
        ]);

        if ($validated['visibility'] === ListVisibility::Specific->value && ! empty($validated['member_ids'])) {
            $list->members()->sync($validated['member_ids']);
        }

        return response()->json($list, 201);
    }

    public function update(Request $request, FamilyList $familyList): JsonResponse
    {
        if ($familyList->user_id !== $request->user()->familyOwnerId()) {
            abort(403);
        }

        $ownerId = $request->user()->familyOwnerId();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(array_column(\App\Enums\ListType::cases(), 'value'))],
            'visibility' => ['required', 'string', Rule::in(array_column(ListVisibility::cases(), 'value'))],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['integer', Rule::exists('family_members', 'id')->where('user_id', $ownerId)],
        ]);

        $familyList->update([
            'name' => $validated['name'],
            'type' => $validated['type'],
            'visibility' => $validated['visibility'],
        ]);

        if ($validated['visibility'] === ListVisibility::Specific->value) {
            $familyList->members()->sync($validated['member_ids'] ?? []);
        } else {
            $familyList->members()->detach();
        }

        return response()->json($familyList);
    }

    public function togglePin(Request $request, FamilyList $familyList): JsonResponse
    {
        if ($familyList->user_id !== $request->user()->familyOwnerId()) {
            abort(403);
        }

        $familyList->update(['is_pinned' => ! $familyList->is_pinned]);

        return response()->json($familyList);
    }

    public function destroy(Request $request, FamilyList $familyList): JsonResponse
    {
        if ($familyList->user_id !== $request->user()->familyOwnerId()) {
            abort(403);
        }

        $familyList->delete();

        return response()->json(null, 204);
    }

    private function getVisibleLists(Request $request)
    {
        return FamilyList::visibleTo($request->user());
    }

    private function authorizeListAccess(Request $request, FamilyList $familyList): void
    {
        abort_unless($familyList->isVisibleTo($request->user()), 403);
    }

    /**
     * Frequently bought items for this family (checked off at least once,
     * including ones since cleared) that aren't already on the list.
     *
     * @return list<string>
     */
    private function suggestions(FamilyList $list): array
    {
        if (! in_array($list->type, [\App\Enums\ListType::Grocery, \App\Enums\ListType::Shopping], true)) {
            return [];
        }

        $onList = $list->items->pluck('name')->map(fn ($n) => mb_strtolower($n))->all();

        return \App\Models\FamilyListItem::withTrashed()
            ->where('is_completed', true)
            ->whereHas('familyList', fn ($q) => $q->where('user_id', $list->user_id)->where('type', $list->type))
            ->selectRaw('MIN(name) as name, COUNT(*) as bought')
            ->groupByRaw('LOWER(name)')
            ->orderByDesc('bought')
            ->limit(30)
            ->pluck('name')
            ->reject(fn ($n) => in_array(mb_strtolower($n), $onList, true))
            ->take(12)
            ->values()
            ->all();
    }

    private function getFamilyMembers(Request $request)
    {
        $user = $request->user();
        $ownerId = $user->familyOwnerId();

        return FamilyMember::where('user_id', $ownerId)->orderBy('name')->get();
    }
}
