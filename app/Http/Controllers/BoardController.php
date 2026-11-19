<?php

namespace App\Http\Controllers;

use App\Models\BoardPost;
use App\Models\FamilyMember;
use App\Models\User;
use App\Notifications\PushAlert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** A simple family message board: short notes, pin the important ones. Viewing/posting reuse lists.*; moderating (pin, delete others') needs family.edit, since kids hold lists.edit/delete. */
class BoardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $posts = BoardPost::where('user_id', $user->familyOwnerId())
            ->with('author:id,name')
            ->orderByDesc('is_pinned')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn (BoardPost $p) => [
                'id' => $p->id,
                'body' => $p->body,
                'is_pinned' => $p->is_pinned,
                'author' => $p->author->name ?? 'Someone',
                'mine' => $p->author_id === $user->id,
                'created_at' => $p->created_at->toIso8601String(),
            ]);

        return Inertia::render('board/Index', [
            'posts' => $posts,
            'can' => [
                'post' => $user->can('lists.create'),
                'moderate' => $user->can('family.edit'),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:1000']]);

        $user = $request->user();
        $ownerId = $user->familyOwnerId();

        $post = BoardPost::create([
            'user_id' => $ownerId,
            'author_id' => $user->id,
            'body' => trim($data['body']),
        ]);

        $recipientIds = FamilyMember::where('user_id', $ownerId)->whereNotNull('linked_user_id')
            ->pluck('linked_user_id')->push($ownerId)->unique()->reject(fn ($id) => $id === $user->id);

        User::whereIn('id', $recipientIds)->get()->each(fn (User $u) => $u->notify(new PushAlert(
            "{$user->name} posted on the board",
            mb_strimwidth($post->body, 0, 120, '…'),
            '/board',
            'board',
        )));

        return response()->json(['id' => $post->id], 201);
    }

    public function togglePin(Request $request, BoardPost $post): JsonResponse
    {
        $this->authorizeFamily($request, $post);
        abort_unless($request->user()->can('family.edit'), 403);

        $post->update(['is_pinned' => ! $post->is_pinned]);

        return response()->json(['is_pinned' => $post->is_pinned]);
    }

    public function destroy(Request $request, BoardPost $post): JsonResponse
    {
        $this->authorizeFamily($request, $post);

        $user = $request->user();
        abort_unless($post->author_id === $user->id || $user->can('family.edit'), 403);

        $post->delete();

        return response()->json(null, 204);
    }

    private function authorizeFamily(Request $request, BoardPost $post): void
    {
        abort_unless($post->user_id === $request->user()->familyOwnerId(), 403);
    }
}
