<?php

namespace App\Http\Controllers;

use App\Models\Chore;
use App\Models\ChoreAssignment;
use App\Models\ChoreCompletion;
use App\Models\FamilyMember;
use App\Notifications\PushAlert;
use App\Services\FamilyAlerts;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChoreCompletionController extends Controller
{
    private const BACKFILL_DAYS = 7;

    public function toggle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'chore_assignment_id' => ['required', 'integer', 'exists:chore_assignments,id'],
            'date' => ['required', 'date'],
        ]);

        $assignment = ChoreAssignment::findOrFail($validated['chore_assignment_id']);
        $chore = Chore::findOrFail($assignment->chore_id);

        // Verify ownership
        $user = $request->user();
        $ownerId = $user->familyOwnerId();
        if ($chore->user_id !== $ownerId) {
            abort(403);
        }

        // Points are spendable (rewards), so completions are tightly bounded:
        // no future dates (one day of slack for timezones) and a short backfill window.
        $date = Carbon::parse($validated['date'])->startOfDay();
        $today = $user->localNow()->startOfDay();
        if ($date->gt($today->copy()->addDay()) || $date->lt($today->copy()->subDays(self::BACKFILL_DAYS))) {
            abort(422, 'That date is outside the allowed window for completing chores.');
        }

        $canManage = $user->can('chores.edit');

        // Without chores.edit (e.g. kids) you can only complete your own chores.
        if (! $canManage) {
            $ownMemberId = FamilyMember::where('user_id', $ownerId)->where('linked_user_id', $user->id)->value('id');

            abort_unless($ownMemberId !== null && $assignment->family_member_id === $ownMemberId, 403);
        }

        $existing = ChoreCompletion::withoutGlobalScope('approved')
            ->where('chore_assignment_id', $assignment->id)
            ->whereDate('completed_date', $validated['date'])
            ->first();

        if ($existing) {
            // Once a parent has approved a chore that needs approval, a kid can't quietly
            // un-do it (that would just be a way to re-submit it).
            if (! $canManage && $chore->requires_approval && $existing->status === ChoreCompletion::APPROVED) {
                abort(403, 'This chore was already approved.');
            }

            $existing->delete();

            return response()->json([
                'completed' => false,
                'status' => null,
                'points_earned' => 0,
            ]);
        }

        $needsApproval = $chore->requires_approval && ! $canManage;

        $completion = ChoreCompletion::create([
            'chore_assignment_id' => $assignment->id,
            'family_member_id' => $assignment->family_member_id,
            'completed_date' => $validated['date'],
            'points_earned' => $chore->points,
            'status' => $needsApproval ? ChoreCompletion::PENDING : ChoreCompletion::APPROVED,
            'approved_by' => $needsApproval ? null : $user->id,
            'approved_at' => $needsApproval ? null : now(),
        ]);

        if ($needsApproval) {
            app(FamilyAlerts::class)->notifyManagers($ownerId, $user->id, new PushAlert(
                'Chore to approve',
                "{$assignment->familyMember->display_name} finished: {$chore->name} (+{$chore->points})",
                '/chores',
                'chore-approval',
            ));
        }

        return response()->json([
            'completed' => true,
            'status' => $completion->status,
            'points_earned' => $needsApproval ? 0 : $completion->points_earned,
        ]);
    }

    /** Approve or reject a pending completion (parents / chores.edit only). */
    public function resolve(Request $request, int $completion): JsonResponse
    {
        $data = $request->validate(['status' => ['required', 'in:approved,rejected']]);

        $record = ChoreCompletion::withoutGlobalScope('approved')
            ->with(['assignment.chore', 'familyMember.linkedUser'])
            ->findOrFail($completion);

        abort_unless($record->assignment->chore->user_id === $request->user()->familyOwnerId(), 403);
        abort_unless($record->status === ChoreCompletion::PENDING, 422, 'Already resolved.');

        $choreName = $record->assignment->chore->name;
        $recipient = $record->familyMember?->linkedUser;
        $approved = $data['status'] === 'approved';

        if ($approved) {
            $record->update([
                'status' => ChoreCompletion::APPROVED,
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);
        } else {
            $record->delete();
        }

        if ($recipient && $recipient->id !== $request->user()->id) {
            $recipient->notify(new PushAlert(
                $approved ? 'Chore approved ✅' : 'Chore needs another go',
                $approved ? "{$choreName}: +{$record->points_earned} points" : "{$choreName} wasn't approved — give it another try.",
                '/my-day',
                'chore-result',
            ));
        }

        return response()->json(['status' => $data['status']]);
    }
}
