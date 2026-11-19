<?php

namespace App\Http\Controllers;

use App\Models\FamilyMember;
use App\Models\SavingsEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SavingsController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $ownerId = $user->familyOwnerId();
        $canManage = $user->can('family.edit');

        $members = FamilyMember::where('user_id', $ownerId)->orderBy('name')
            // Without family.edit you only ever see your own account.
            ->when(! $canManage, fn ($q) => $q->where('linked_user_id', $user->id))
            ->get(['id', 'name', 'nickname', 'color']);

        $entries = SavingsEntry::where('user_id', $ownerId)
            ->whereIn('family_member_id', $members->pluck('id'))
            ->orderByDesc('entry_date')->orderByDesc('id')
            ->get(['id', 'family_member_id', 'amount', 'note', 'entry_date'])
            ->groupBy('family_member_id');

        return Inertia::render('savings/Index', [
            'accounts' => $members->map(fn (FamilyMember $m) => [
                'id' => $m->id,
                'name' => $m->display_name,
                'color' => $m->color,
                'balance' => round((float) ($entries[$m->id] ?? collect())->sum('amount'), 2),
                'entries' => ($entries[$m->id] ?? collect())->take(25)->map(fn ($e) => [
                    'id' => $e->id,
                    'amount' => (float) $e->amount,
                    'note' => $e->note,
                    'date' => $e->entry_date->toDateString(),
                ])->values(),
            ])->values(),
            'can' => ['manage' => $canManage],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $ownerId = $user->familyOwnerId();

        $data = $request->validate([
            'family_member_id' => ['required', 'integer', Rule::exists('family_members', 'id')->where('user_id', $ownerId)],
            'amount' => ['required', 'numeric', 'not_in:0', 'between:-100000,100000'],
            'note' => ['nullable', 'string', 'max:255'],
            'entry_date' => ['nullable', 'date'],
        ]);

        /** @var SavingsEntry $entry */
        $entry = $user->familyOwner()->savingsEntries()->create([
            'family_member_id' => $data['family_member_id'],
            'amount' => round((float) $data['amount'], 2),
            'note' => $data['note'] ?? null,
            'entry_date' => $data['entry_date'] ?? $user->localNow()->toDateString(),
            'created_by' => $user->id,
        ]);

        return response()->json(['id' => $entry->id], 201);
    }

    public function destroy(Request $request, int $entry): JsonResponse
    {
        $record = SavingsEntry::findOrFail($entry);
        abort_unless($record->user_id === $request->user()->familyOwnerId(), 403);

        $record->delete();

        return response()->json(null, 204);
    }
}
