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

        $familyMembers = $this->getFamilyMembers($request);

        return Inertia::render('lists/Show', [
            'list' => $familyList,
            'familyMembers' => $familyMembers,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(array_column(\App\Enums\ListType::cases(), 'value'))],
            'visibility' => ['required', 'string', Rule::in(array_column(ListVisibility::cases(), 'value'))],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['integer', 'exists:family_members,id'],
        ]);

        /** @var FamilyList $list */
        $list = $request->user()->familyLists()->create([
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
        if ($familyList->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(array_column(\App\Enums\ListType::cases(), 'value'))],
            'visibility' => ['required', 'string', Rule::in(array_column(ListVisibility::cases(), 'value'))],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['integer', 'exists:family_members,id'],
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
        if ($familyList->user_id !== $request->user()->id) {
            abort(403);
        }

        $familyList->update(['is_pinned' => ! $familyList->is_pinned]);

        return response()->json($familyList);
    }

    public function destroy(Request $request, FamilyList $familyList): JsonResponse
    {
        if ($familyList->user_id !== $request->user()->id) {
            abort(403);
        }

        $familyList->delete();

        return response()->json(null, 204);
    }

    private function getVisibleLists(Request $request)
    {
        $user = $request->user();

        // Find if this user is a linked family member
        $linkedMember = $user->linkedFamilyMember();

        if (! $linkedMember) {
            // User is a family owner — only see their own lists
            return $user->familyLists();
        }

        // User is a linked family member — see own lists + family owner's visible lists
        $familyOwnerId = $linkedMember->user_id;
        $memberRole = $linkedMember->role?->value;
        $memberId = $linkedMember->id;

        return FamilyList::where(function ($query) use ($user, $familyOwnerId, $memberRole, $memberId) {
            // Always include user's own lists
            $query->where('user_id', $user->id)
                // Include family owner's lists based on visibility
                ->orWhere(function ($q) use ($familyOwnerId, $memberRole, $memberId) {
                    $q->where('user_id', $familyOwnerId)
                        ->where(function ($vis) use ($memberRole, $memberId) {
                            $vis->where('visibility', ListVisibility::Everyone->value)
                                ->when($memberRole === 'parent', fn ($q) => $q->orWhere('visibility', ListVisibility::Parents->value))
                                ->when($memberRole === 'child', fn ($q) => $q->orWhere('visibility', ListVisibility::Children->value))
                                ->orWhere(function ($specific) use ($memberId) {
                                    $specific->where('visibility', ListVisibility::Specific->value)
                                        ->whereHas('members', fn ($q) => $q->where('family_member_id', $memberId));
                                });
                        });
                });
        });
    }

    private function authorizeListAccess(Request $request, FamilyList $familyList): void
    {
        $user = $request->user();

        // Owner always has access
        if ($familyList->user_id === $user->id) {
            return;
        }

        // Check if user is a linked family member of the list owner's family
        $linkedMember = FamilyMember::where('linked_user_id', $user->id)
            ->where('user_id', $familyList->user_id)
            ->first();

        if (! $linkedMember) {
            abort(403);
        }

        $visibility = $familyList->visibility;
        $memberRole = $linkedMember->role?->value;

        if ($visibility === ListVisibility::Everyone) {
            return;
        }

        if ($visibility === ListVisibility::Parents && $memberRole === 'parent') {
            return;
        }

        if ($visibility === ListVisibility::Children && $memberRole === 'child') {
            return;
        }

        if ($visibility === ListVisibility::Specific) {
            if ($familyList->members()->where('family_member_id', $linkedMember->id)->exists()) {
                return;
            }
        }

        abort(403);
    }

    private function getFamilyMembers(Request $request)
    {
        $user = $request->user();
        $ownerId = $user->familyOwnerId();

        return FamilyMember::where('user_id', $ownerId)->orderBy('name')->get();
    }
}
