<?php

use App\Models\BoardPost;
use App\Models\FamilyMember;
use App\Models\User;
use App\Notifications\PushAlert;
use Illuminate\Support\Facades\Notification;

function boardFamily(): array
{
    $owner = createAdminUser();
    $kid = User::factory()->create();
    $kid->assignRole('child');
    FamilyMember::factory()->create(['user_id' => $owner->id, 'role' => 'child', 'linked_user_id' => $kid->id]);

    return [$owner, $kid];
}

test('family members can post and everyone else is notified', function () {
    Notification::fake();
    [$owner, $kid] = boardFamily();
    $owner->updatePushSubscription('https://push.example.com/o', 'k', 'a');

    $this->actingAs($kid)->postJson('/board', ['body' => 'Pizza tonight?'])->assertCreated();

    expect(BoardPost::first())->user_id->toBe($owner->id)->author_id->toBe($kid->id);
    Notification::assertSentTo($owner, PushAlert::class, fn ($n) => str_contains($n->body, 'Pizza'));
    Notification::assertNotSentTo($kid, PushAlert::class);
});

test('the board only shows the family\'s own posts, pinned first', function () {
    [$owner, $kid] = boardFamily();
    $other = createAdminUser();
    BoardPost::create(['user_id' => $owner->id, 'author_id' => $owner->id, 'body' => 'older pinned', 'is_pinned' => true, 'created_at' => now()->subDay()]);
    BoardPost::create(['user_id' => $owner->id, 'author_id' => $kid->id, 'body' => 'newer']);
    BoardPost::create(['user_id' => $other->id, 'author_id' => $other->id, 'body' => 'SECRET']);

    $this->actingAs($kid)->get('/board')->assertInertia(fn ($p) => $p
        ->has('posts', 2)
        ->where('posts.0.body', 'older pinned')
        ->where('posts.1.mine', true));
});

test('kids can delete their own posts but not pin or delete others', function () {
    [$owner, $kid] = boardFamily();
    $mine = BoardPost::create(['user_id' => $owner->id, 'author_id' => $kid->id, 'body' => 'mine']);
    $theirs = BoardPost::create(['user_id' => $owner->id, 'author_id' => $owner->id, 'body' => 'theirs']);

    $this->actingAs($kid)->patchJson("/board/{$mine->id}/pin")->assertForbidden();
    $this->actingAs($kid)->deleteJson("/board/{$theirs->id}")->assertForbidden();
    $this->actingAs($kid)->deleteJson("/board/{$mine->id}")->assertNoContent();

    $this->actingAs($owner)->patchJson("/board/{$theirs->id}/pin")->assertOk()->assertJson(['is_pinned' => true]);
    $this->actingAs($owner)->deleteJson("/board/{$theirs->id}")->assertNoContent();
});

test('other families cannot touch a post, and bodies are validated', function () {
    [$owner] = boardFamily();
    $post = BoardPost::create(['user_id' => $owner->id, 'author_id' => $owner->id, 'body' => 'x']);
    $stranger = createAdminUser();

    $this->actingAs($stranger)->patchJson("/board/{$post->id}/pin")->assertForbidden();
    $this->actingAs($stranger)->deleteJson("/board/{$post->id}")->assertForbidden();
    $this->actingAs($owner)->postJson('/board', ['body' => ''])->assertUnprocessable();
    $this->actingAs($owner)->postJson('/board', ['body' => str_repeat('a', 1001)])->assertUnprocessable();
});
