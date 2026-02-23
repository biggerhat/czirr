<?php

namespace App\Http\Controllers;

use App\Enums\DefaultRole;
use App\Enums\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        $roles = Role::with('permissions')->get()->map(fn (Role $role) => [
            'id' => $role->id,
            'name' => $role->name,
            'is_default' => in_array($role->name, DefaultRole::names()),
            'permissions' => $role->permissions->pluck('name')->toArray(),
            'users_count' => $role->users()->count(),
        ]);

        return response()->json([
            'roles' => $roles,
            'availablePermissions' => Permission::grouped(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role = Role::create(['name' => $validated['name']]);
        $role->syncPermissions($validated['permissions']);

        return response()->json([
            'id' => $role->id,
            'name' => $role->name,
            'is_default' => false,
            'permissions' => $role->permissions->pluck('name')->toArray(),
            'users_count' => 0,
        ], 201);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        $rules = [
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ];

        // Default roles can't be renamed
        if (! in_array($role->name, DefaultRole::names())) {
            $rules['name'] = ['sometimes', 'string', 'max:255', 'unique:roles,name,' . $role->id];
        }

        $validated = $request->validate($rules);

        if (isset($validated['name']) && ! in_array($role->name, DefaultRole::names())) {
            $role->update(['name' => $validated['name']]);
        }

        $role->syncPermissions($validated['permissions']);

        return response()->json([
            'id' => $role->id,
            'name' => $role->name,
            'is_default' => in_array($role->name, DefaultRole::names()),
            'permissions' => $role->permissions->pluck('name')->toArray(),
            'users_count' => $role->users()->count(),
        ]);
    }

    public function destroy(Role $role): JsonResponse
    {
        if (in_array($role->name, DefaultRole::names())) {
            return response()->json(['message' => 'Default roles cannot be deleted.'], 422);
        }

        if ($role->users()->count() > 0) {
            return response()->json(['message' => 'Cannot delete a role that has users assigned.'], 422);
        }

        $role->delete();

        return response()->json(null, 204);
    }
}
