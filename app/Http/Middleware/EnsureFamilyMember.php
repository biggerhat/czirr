<?php

namespace App\Http\Middleware;

use App\Models\FamilyMember;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnsureFamilyMember
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $isFamilyOwner = $user->familyMembers()->exists();
            $isLinkedMember = FamilyMember::where('linked_user_id', $user->id)->exists();

            if (! $isFamilyOwner && ! $isLinkedMember) {
                return Inertia::render('FamilyRequired')->toResponse($request);
            }
        }

        return $next($request);
    }
}
