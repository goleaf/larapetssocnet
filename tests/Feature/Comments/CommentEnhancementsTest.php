<?php

use App\Models\Content\Comment;
use App\Models\Content\Post;
use App\Models\Content\Reaction;
use App\Models\Identity\User;
use App\Notifications\NewCommentThreadReply;
use App\Services\CommentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('autosaves restores and clears a per-post comment draft', function (): void {
    $viewer = User::factory()->create();
    $post = Post::factory()->for(User::factory())->create([
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);

    Livewire::actingAs($viewer)
        ->test('posts.comments-thread', ['post' => $post])
        ->set('body', 'I will finish this thought later.')
        ->call('autosaveDraft')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('comment_drafts', [
        'user_id' => $viewer->id,
        'post_id' => $post->id,
        'body' => 'I will finish this thought later.',
    ]);

    Livewire::actingAs($viewer)
        ->test('posts.comments-thread', ['post' => $post])
        ->assertSet('body', 'I will finish this thought later.')
        ->assertSet('draftRestored', true)
        ->assertSee('Draft restored')
        ->call('createComment')
        ->assertSet('body', '')
        ->assertSet('draftRestored', false);

    $this->assertDatabaseMissing('comment_drafts', [
        'user_id' => $viewer->id,
        'post_id' => $post->id,
    ]);
});

it('searches gifs server side and stores the selected gif on a comment', function (): void {
    config()->set('services.gif.key', 'test-key');
    config()->set('services.gif.endpoint', 'https://tenor.example/search');

    Http::fake([
        'tenor.example/*' => Http::response([
            'results' => [
                [
                    'id' => 'gif-1',
                    'content_description' => 'Happy dog',
                    'media_formats' => [
                        'gif' => ['url' => 'https://cdn.example/happy-dog.gif'],
                        'tinygif' => ['url' => 'https://cdn.example/happy-dog-small.gif'],
                    ],
                ],
            ],
        ]),
    ]);

    $viewer = User::factory()->create();
    $post = Post::factory()->for(User::factory())->create([
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);

    Livewire::actingAs($viewer)
        ->test('posts.comments-thread', ['post' => $post])
        ->set('gifSearch', 'happy dog')
        ->call('searchGifs')
        ->assertSet('gifResults.0.title', 'Happy dog')
        ->call('selectGif', 0)
        ->assertSet('selectedGif.gif_url', 'https://cdn.example/happy-dog.gif')
        ->call('createComment')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('comments', [
        'post_id' => $post->id,
        'user_id' => $viewer->id,
        'body' => '',
        'gif_url' => 'https://cdn.example/happy-dog.gif',
        'gif_preview_url' => 'https://cdn.example/happy-dog-small.gif',
        'gif_title' => 'Happy dog',
        'gif_provider' => 'tenor',
    ]);
});

it('filters and highlights comments on busy post pages', function (): void {
    $viewer = User::factory()->create();
    $post = Post::factory()->for(User::factory())->create([
        'visibility' => Post::VISIBILITY_PUBLIC,
        'comments_count' => 51,
    ]);

    Comment::factory()
        ->count(50)
        ->for($post)
        ->for(User::factory(), 'user')
        ->create([
            'body' => 'ordinary comment',
            'body_html' => 'ordinary comment',
        ]);

    Comment::factory()->for($post)->for($viewer, 'user')->create([
        'body' => 'Needle comment worth finding',
        'body_html' => 'Needle comment worth finding',
        'quality_score' => 30,
    ]);

    Livewire::actingAs($viewer)
        ->test('posts.comments-thread', ['post' => $post, 'fullPage' => true])
        ->assertSee('Search comments')
        ->set('search', 'Needle')
        ->assertSee('comment worth finding')
        ->assertSee('<mark', false)
        ->assertDontSee('ordinary comment');
});

it('shows eager-loaded reaction avatars for comment reactions', function (): void {
    $viewer = User::factory()->create();
    $reactor = User::factory()->create([
        'name' => 'Reactor Person',
        'username' => 'reactor-person',
    ]);
    $post = Post::factory()->for(User::factory())->create([
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);
    $comment = Comment::factory()->for($post)->for(User::factory(), 'user')->create();

    $comment->reactions()->create([
        'user_id' => $reactor->id,
        'type' => Reaction::TYPE_PAW,
    ]);
    $comment->forceFill([
        'reactions_count' => 1,
        'paw_count' => 1,
    ])->save();

    $threadComment = app(CommentService::class)
        ->threadForPost($post, $viewer)
        ->firstOrFail();

    expect($threadComment->reaction_reactors)->toHaveCount(1)
        ->and($threadComment->reaction_reactors[0]['username'])->toBe('reactor-person');
});

it('subscribes active thread participants and notifies them about later replies', function (): void {
    Notification::fake();

    $participant = User::factory()->create();
    $other = User::factory()->create();
    $third = User::factory()->create();
    $post = Post::factory()->for(User::factory())->create([
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);

    Livewire::actingAs($participant)
        ->test('posts.comments-thread', ['post' => $post])
        ->set('body', 'First thread comment')
        ->call('createComment');

    $root = Comment::query()->where('body', 'First thread comment')->firstOrFail();

    Livewire::actingAs($participant)
        ->test('posts.comments-thread', ['post' => $post])
        ->set('replyBodies.'.$root->id, 'Second comment in this thread')
        ->call('createReply', $root->id);

    $this->assertDatabaseHas('comment_thread_subscriptions', [
        'user_id' => $participant->id,
        'root_comment_id' => $root->id,
        'unsubscribed_at' => null,
    ]);

    Livewire::actingAs($other)
        ->test('posts.comments-thread', ['post' => $post])
        ->set('replyBodies.'.$root->id, 'A branch reply')
        ->call('createReply', $root->id);

    $branch = Comment::query()->where('body', 'A branch reply')->firstOrFail();

    Livewire::actingAs($third)
        ->test('posts.comments-thread', ['post' => $post])
        ->set('replyBodies.'.$branch->id, 'A later reply for subscribers')
        ->call('createReply', $branch->id);

    Notification::assertSentTo($participant, NewCommentThreadReply::class);
});

it('translates comments through the server side translation adapter and caches the result', function (): void {
    config()->set('services.translation.endpoint', 'https://translate.example/comment');
    config()->set('services.translation.key', 'translation-key');

    Http::fake([
        'translate.example/*' => Http::response([
            'translated_text' => 'Hello friend',
        ]),
    ]);

    $viewer = User::factory()->create();
    $post = Post::factory()->for(User::factory())->create([
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);
    $comment = Comment::factory()->for($post)->for(User::factory(), 'user')->create([
        'body' => 'Hola amigo',
        'body_html' => 'Hola amigo',
        'language_code' => 'es',
    ]);

    Livewire::actingAs($viewer)
        ->test('posts.comments-thread', ['post' => $post, 'fullPage' => true])
        ->call('translateComment', $comment->id)
        ->assertSet('translations.'.$comment->id, 'Hello friend')
        ->assertSee('Hello friend');

    $this->assertDatabaseHas('comment_translations', [
        'comment_id' => $comment->id,
        'source_language' => 'es',
        'target_language' => 'en',
        'translated_body' => 'Hello friend',
    ]);
});
