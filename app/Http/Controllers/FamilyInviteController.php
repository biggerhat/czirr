<?php

namespace App\Http\Controllers;

use App\Enums\FamilyRole;
use App\Models\FamilyInvite;
use App\Models\FamilyMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FamilyInviteController extends Controller
{
    /** Parents create a shareable link. The plain token is only ever returned here. */
    public function store(Request $request): JsonResponse
    {
        $ownerId = $request->user()->familyOwnerId();

        $data = $request->validate([
            'family_member_id' => ['nullable', 'integer', Rule::exists('family_members', 'id')->where('user_id', $ownerId)],
            'name' => ['nullable', 'string', 'max:255'],
            'role' => ['required', Rule::in(['parent', 'child'])],
        ]);

        $member = null;
        if (! empty($data['family_member_id'])) {
            $member = FamilyMember::findOrFail($data['family_member_id']);
            abort_if($member->linked_user_id !== null, 422, 'That family member already has an account.');
            $data['role'] = $member->role->value === 'child' ? 'child' : 'parent';
        }

        [$invite, $token] = FamilyInvite::issue([
            'user_id' => $ownerId,
            'created_by' => $request->user()->id,
            'family_member_id' => $member?->id,
            'name' => $member ? null : ($data['name'] ?? null),
            'role' => $data['role'],
        ]);

        return response()->json([
            'url' => route('invite.show', $token),
            'expires_at' => $invite->expires_at->toIso8601String(),
        ], 201);
    }

    /** Public landing page. Guests are sent to log in / register and come back here. */
    public function show(Request $request, string $token): Response|RedirectResponse
    {
        $invite = FamilyInvite::findByToken($token);

        if (! $invite || ! $invite->isUsable()) {
            return Inertia::render('invite/Show', ['state' => 'invalid']);
        }

        if (! $request->user()) {
            // After login/register Fortify redirects to the intended URL — this page.
            $request->session()->put('url.intended', route('invite.show', $token));
        }

        $owner = $invite->owner;
        $joinedAs = $invite->family_member_id ? $invite->member->name : $invite->name;

        return Inertia::render('invite/Show', [
            'state' => $request->user() ? 'ready' : 'guest',
            'family' => $owner->name,
            'joinedAs' => $joinedAs,
            'role' => $invite->role,
            'token' => $token,
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $user = $request->user();

        $result = DB::transaction(function () use ($token, $user) {
            $invite = FamilyInvite::where('token_hash', FamilyInvite::hash($token))->lockForUpdate()->first();

            if (! $invite || ! $invite->isUsable()) {
                return 'This invite is no longer valid.';
            }

            if (FamilyMember::where('linked_user_id', $user->id)->exists() || $user->familyMembers()->exists()) {
                return 'Your account already belongs to a family.';
            }

            $role = $invite->role === 'child' ? FamilyRole::Child : FamilyRole::Parent;

            if ($invite->family_member_id) {
                $member = FamilyMember::lockForUpdate()->find($invite->family_member_id);
                if (! $member || $member->linked_user_id !== null) {
                    return 'This family member has already been claimed.';
                }
                $member->update(['linked_user_id' => $user->id]);
                $role = $member->role;
            } else {
                FamilyMember::create([
                    'user_id' => $invite->user_id,
                    'name' => $invite->name ?: $user->name,
                    'role' => $role,
                    'color' => 'blue',
                    'linked_user_id' => $user->id,
                ]);
            }

            if (! $user->hasAnyRole(\App\Enums\DefaultRole::names())) {
                $user->assignRole($role === FamilyRole::Child ? 'child' : 'parent');
            }

            $invite->update(['accepted_at' => now(), 'accepted_by' => $user->id]);

            return null;
        });

        if ($result !== null) {
            return redirect()->route('invite.show', $token)->with('error', $result);
        }

        return redirect('/dashboard')->with('status', 'Welcome to the family!');
    }
}
