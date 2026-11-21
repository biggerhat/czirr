<?php

namespace App\Http\Controllers;

use App\Enums\DefaultRole;
use App\Enums\FamilyRole;
use App\Enums\Permission;
use App\Models\FamilyMember;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class FamilyMemberController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        // Resolve the family owner — for linked members this is the user who
        // owns the family; for family owners it's themselves.
        $ownerId = $user->familyOwnerId();
        $isOwner = $ownerId === $user->id;

        // Auto-create a self-linked family member only for family owners
        // (users not linked into another family).
        if ($isOwner) {
            $hasSelf = $user->familyMembers()->where('linked_user_id', $user->id)->exists();
            if (! $hasSelf) {
                $user->familyMembers()->create([
                    'name' => $user->name,
                    'role' => FamilyRole::Parent,
                    'color' => 'blue',
                    'linked_user_id' => $user->id,
                ]);

                if (! $user->hasAnyRole(DefaultRole::names())) {
                    $user->assignRole('parent');
                }
            }
        }

        $familyMembers = FamilyMember::where('user_id', $ownerId)
            ->with('linkedUser:id,name,email')
            ->orderBy('name')
            ->get()
            ->map(function (FamilyMember $member) {
                $data = $member->toArray();
                $data['spatie_role'] = $member->linkedUser?->getRoleNames()->first();

                return $data;
            });

        // Users that could be linked (all users not already linked to a family member owned by this family)
        $alreadyLinkedIds = FamilyMember::where('user_id', $ownerId)
            ->whereNotNull('linked_user_id')
            ->pluck('linked_user_id');

        $linkableUsers = User::whereNotIn('id', $alreadyLinkedIds)
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();

        $roles = Role::with('permissions')->get()->map(fn (Role $role) => [
            'id' => $role->id,
            'name' => $role->name,
            'is_default' => in_array($role->name, DefaultRole::names()),
            'permissions' => $role->permissions->pluck('name')->toArray(),
            'users_count' => $role->users()->count(),
        ]);

        return Inertia::render('family/Index', [
            'familyMembers' => $familyMembers,
            'linkableUsers' => $linkableUsers,
            'roles' => $roles,
            'availablePermissions' => Permission::grouped(),
            'canManageRoles' => $user->can('roles.manage'),
            'can' => [
                'create' => $user->can('family.create'),
                'edit' => $user->can('family.edit'),
                'delete' => $user->can('family.delete'),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $ownerId = $user->familyOwnerId();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'nickname' => ['nullable', 'string', 'max:255'],
            'role' => ['required', Rule::enum(FamilyRole::class)],
            'color' => ['required', 'string', 'in:rose,orange,amber,emerald,cyan,blue,violet,pink'],
            'linked_user_id' => [
                'nullable',
                'integer',
                'exists:users,id',
                Rule::unique('family_members')->where('user_id', $ownerId),
            ],
        ]);

        /** @var FamilyMember $member */
        $member = FamilyMember::create([...$validated, 'user_id' => $ownerId]);

        // Assign matching spatie role to linked user
        if ($member->linked_user_id) {
            $linkedUser = User::find($member->linked_user_id);
            if ($linkedUser && ! $linkedUser->hasAnyRole(DefaultRole::names())) {
                $spatieRole = $member->role?->value === 'child' ? 'child' : 'parent';
                $linkedUser->assignRole($spatieRole);
            }
        }

        $member->load('linkedUser:id,name,email');

        $data = $member->toArray();
        $data['spatie_role'] = $member->linkedUser?->getRoleNames()->first();

        return response()->json($data, 201);
    }

    public function update(Request $request, FamilyMember $familyMember): JsonResponse
    {
        $ownerId = $request->user()->familyOwnerId();
        abort_unless($familyMember->user_id === $ownerId, 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'nickname' => ['nullable', 'string', 'max:255'],
            'role' => ['required', Rule::enum(FamilyRole::class)],
            'color' => ['required', 'string', 'in:rose,orange,amber,emerald,cyan,blue,violet,pink'],
            'linked_user_id' => [
                'nullable',
                'integer',
                'exists:users,id',
                Rule::unique('family_members')
                    ->where('user_id', $ownerId)
                    ->ignore($familyMember->id),
            ],
        ]);

        $this->guardAdminMember($request, $familyMember, $validated);

        $oldRole = $familyMember->role?->value;
        $familyMember->update($validated);

        // Sync spatie role when family role changes on linked member
        if ($familyMember->linked_user_id && $oldRole !== $validated['role']) {
            $linkedUser = User::find($familyMember->linked_user_id);
            if ($linkedUser) {
                $spatieRole = $validated['role'] === 'child' ? 'child' : 'parent';
                $linkedUser->syncRoles([$spatieRole]);
            }
        }

        $familyMember->load('linkedUser:id,name,email');

        $data = $familyMember->toArray();
        $data['spatie_role'] = $familyMember->linkedUser?->getRoleNames()->first();

        return response()->json($data);
    }

    public function destroy(Request $request, FamilyMember $familyMember): JsonResponse
    {
        abort_unless($familyMember->user_id === $request->user()->familyOwnerId(), 403);

        // Prevent deleting your own linked family member
        if ($familyMember->linked_user_id === $request->user()->id) {
            return response()->json(['message' => 'You cannot remove your own family member entry.'], 422);
        }

        $this->guardAdminMember($request, $familyMember);

        $familyMember->delete();

        return response()->json(null, 204);
    }

    public function updateRole(Request $request, FamilyMember $familyMember): JsonResponse
    {
        abort_unless($familyMember->user_id === $request->user()->familyOwnerId(), 403);

        if (! $familyMember->linked_user_id) {
            return response()->json(['message' => 'This member is not linked to a user account.'], 422);
        }

        $validated = $request->validate([
            'role' => ['required', 'string', 'exists:roles,name'],
        ]);

        $linkedUser = User::findOrFail($familyMember->linked_user_id);

        if ($linkedUser->hasRole('admin') && $validated['role'] !== 'admin'
            && User::role('admin')->count() <= 1) {
            return response()->json(['message' => 'There must always be at least one admin.'], 422);
        }

        $linkedUser->syncRoles([$validated['role']]);

        return response()->json([
            'spatie_role' => $validated['role'],
        ]);
    }

    /**
     * Admins can only be modified by someone who can manage roles. Without this
     * a parent (family.edit) could demote the admin or detach their account.
     *
     * @param  array<string, mixed>|null  $changes  validated update payload, if any
     */
    private function guardAdminMember(Request $request, FamilyMember $member, ?array $changes = null): void
    {
        $linked = $member->linked_user_id ? User::find($member->linked_user_id) : null;

        if (! $linked || ! $linked->hasRole('admin') || $request->user()->can('roles.manage')) {
            return;
        }

        $touchesIdentity = $changes === null
            || ($changes['role'] ?? null) !== $member->role->value
            || ($changes['linked_user_id'] ?? null) !== $member->linked_user_id;

        abort_if($touchesIdentity, 403, 'Only an admin can change or remove an admin.');
    }
}
