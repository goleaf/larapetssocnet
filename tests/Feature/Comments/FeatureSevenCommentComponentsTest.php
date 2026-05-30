<?php

use App\Actions\Comments\DispatchCommentMentionNotifications;
use App\Actions\Comments\SendCommentMentionNotification;
use App\Livewire\Comments\CommentSection;
use App\Models\Content\Comment;
use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Notifications\Database\Comments\MentionedInComment;
use App\Services\VisibilityService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('opens the class based comment section with compact comment references', function (): void {
    $author = User::factory()->create();
    $viewer = User::factory()->create();
    $post = Post::factory()->for($author)->create([
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);

    Comment::factory()
        ->count(3)
        ->for($post)
        ->for($viewer, 'user')
        ->sequence(
            ['body' => 'First compact comment', 'body_html' => 'First compact comment'],
            ['body' => 'Second compact comment', 'body_html' => 'Second compact comment'],
            ['body' => 'Third compact comment', 'body_html' => 'Third compact comment'],
        )
        ->create();

    $component = Livewire::actingAs($viewer)
        ->test(CommentSection::class, ['postId' => $post->id])
        ->assertSet('isMounted', false)
        ->assertSet('comments', [])
        ->call('openSection')
        ->assertSet('isMounted', true);

    expect($component->get('comments'))
        ->toHaveCount(3)
        ->each->toHaveKey('id');
});

it('hydrates queued comment mention dispatcher with explicit selected columns', function (): void {
    Bus::fake();

    $author = User::factory()->create();
    $recipient = User::factory()->create(['username' => 'mention_target']);
    $post = Post::factory()->for($author)->create([
        'body' => 'A public post mentioning a friend.',
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);
    $comment = Comment::factory()->for($post)->for($author, 'user')->create([
        'body' => '@mention_target hello',
        'body_html' => '@mention_target hello',
    ]);

    $queries = featureSevenCommentQueriesDuring(fn (): null => app(DispatchCommentMentionNotifications::class, [
        'authorId' => (int) $author->getKey(),
        'postId' => (int) $post->getKey(),
        'commentId' => (int) $comment->getKey(),
        'mentionedUsernames' => ['mention_target'],
        'excludedUserIds' => [],
    ])->handle(app(VisibilityService::class)));

    $fullModelSelects = featureSevenFullModelSelects($queries);

    expect($fullModelSelects)->toBeEmpty('Full model selects: '.json_encode($fullModelSelects));
    Bus::assertBatched(fn ($batch): bool => true);
});

it('filters queued comment mention recipients with bounded query counts', function (): void {
    Bus::fake();

    $author = User::factory()->create();
    $recipients = User::factory()
        ->count(6)
        ->sequence(fn ($sequence): array => ['username' => 'batch_mention_'.$sequence->index])
        ->create();
    $post = Post::factory()->for($author)->create([
        'body' => 'A public post mentioning several users.',
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);
    $comment = Comment::factory()->for($post)->for($author, 'user')->create([
        'body' => $recipients->map(fn (User $user): string => '@'.$user->username)->implode(' '),
        'body_html' => 'Batch mentions',
    ]);

    $queries = featureSevenCommentQueriesDuring(fn (): null => app(DispatchCommentMentionNotifications::class, [
        'authorId' => (int) $author->getKey(),
        'postId' => (int) $post->getKey(),
        'commentId' => (int) $comment->getKey(),
        'mentionedUsernames' => $recipients->pluck('username')->all(),
        'excludedUserIds' => [],
    ])->handle(app(VisibilityService::class)));

    $selectQueries = collect($queries)
        ->filter(fn (string $sql): bool => str_starts_with(strtolower(trim($sql)), 'select'))
        ->values();

    expect($selectQueries->count())->toBeLessThanOrEqual(12, 'Select queries: '.json_encode($selectQueries->all()));
    Bus::assertBatched(fn ($batch): bool => true);
});

it('hydrates queued comment mention notification with explicit selected columns', function (): void {
    Notification::fake();

    $author = User::factory()->create();
    $recipient = User::factory()->create(['username' => 'queued_recipient']);
    $post = Post::factory()->for($author)->create([
        'body' => 'A public post with a queued mention notification.',
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);
    $comment = Comment::factory()->for($post)->for($author, 'user')->create([
        'body' => '@queued_recipient hello',
        'body_html' => '@queued_recipient hello',
    ]);

    $queries = featureSevenCommentQueriesDuring(fn (): null => app(SendCommentMentionNotification::class, [
        'authorId' => (int) $author->getKey(),
        'postId' => (int) $post->getKey(),
        'commentId' => (int) $comment->getKey(),
        'recipientId' => (int) $recipient->getKey(),
    ])->handle(app(VisibilityService::class)));

    $fullModelSelects = featureSevenFullModelSelects($queries);

    expect($fullModelSelects)->toBeEmpty('Full model selects: '.json_encode($fullModelSelects));
    Notification::assertSentTo($recipient, MentionedInComment::class);
});

it('deduplicates bursty queued comment mention fanout jobs', function (): void {
    $author = User::factory()->create();
    $recipient = User::factory()->create(['username' => 'burst_target']);
    $post = Post::factory()->for($author)->create([
        'body' => 'A public post with a bursty queued mention notification.',
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);
    $comment = Comment::factory()->for($post)->for($author, 'user')->create([
        'body' => '@burst_target hello',
        'body_html' => '@burst_target hello',
    ]);

    $dispatcher = new DispatchCommentMentionNotifications(
        authorId: (int) $author->getKey(),
        postId: (int) $post->getKey(),
        commentId: (int) $comment->getKey(),
        mentionedUsernames: ['burst_target', 'BURST_TARGET'],
        excludedUserIds: [],
    );

    $job = new SendCommentMentionNotification(
        authorId: (int) $author->getKey(),
        postId: (int) $post->getKey(),
        commentId: (int) $comment->getKey(),
        recipientId: (int) $recipient->getKey(),
    );

    expect($dispatcher)->toBeInstanceOf(ShouldBeUnique::class)
        ->and($dispatcher->uniqueId())->toBe('comment-mentions:'.$comment->getKey())
        ->and($job)->toBeInstanceOf(ShouldBeUnique::class)
        ->and($job->uniqueId())->toBe('comment-mention:'.$comment->getKey().':'.$recipient->getKey());

    $job->handle(app(VisibilityService::class));
    $job->handle(app(VisibilityService::class));

    $mentionNotifications = DB::table('notifications')
        ->where('type', MentionedInComment::class)
        ->where('notifiable_type', $recipient->getMorphClass())
        ->where('notifiable_id', $recipient->getKey())
        ->where('data->comment_id', $comment->getKey())
        ->count();

    expect($mentionNotifications)->toBe(1);
});

/**
 * @return list<string>
 */
function featureSevenCommentQueriesDuring(callable $callback): array
{
    $queries = [];

    DB::listen(function (QueryExecuted $event) use (&$queries): void {
        $queries[] = $event->sql;
    });

    $callback();

    return $queries;
}

/**
 * @param  list<string>  $queries
 * @return list<string>
 */
function featureSevenFullModelSelects(array $queries): array
{
    return collect($queries)
        ->filter(fn (string $sql): bool => preg_match('/^\s*select\s+\*\s+from\s+["`]?(?:posts|comments|users)["`]?/i', $sql) === 1)
        ->values()
        ->all();
}
