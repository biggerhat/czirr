<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'familyRole' => fn () => $user
                ? ($user->linkedFamilyMember()?->role?->value
                    ?? \App\Models\FamilyMember::where('user_id', $user->id)->where('linked_user_id', $user->id)->first()?->role?->value)
                : null,
            'permissions' => fn () => $user
                ? $user->getAllPermissions()->pluck('name')->toArray()
                : [],
            'userRole' => fn () => $user
                ? $user->getRoleNames()->first()
                : null,
        ];
    }
}
