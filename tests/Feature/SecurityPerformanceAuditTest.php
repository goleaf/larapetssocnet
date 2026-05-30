<?php

use App\Models\Content\Comment;
use App\Models\Content\Hashtag;
use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Services\CommentService;
use App\Services\FeedService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('keeps cache object serialization disabled and caches sidebar hashtags as arrays', function (): void {
    expect(config('cache.serializable_classes'))->toBeFalse();

    $service = app(FeedService::class);
    $service->flushTrendingHashtagsCache();

    $hashtag = Hashtag::factory()->create([
        'name' => 'walks',
        'slug' => 'walks',
        'normalized_name' => 'walks',
        'posts_count' => 25,
    ]);

    $trending = $service->trendingHashtags();
    $cachedRows = cachedTrendingHashtagRows();

    expect($trending->first())->toBeInstanceOf(Hashtag::class)
        ->and($trending->first()?->getKey())->toBe($hashtag->getKey())
        ->and($cachedRows)->toBeArray()
        ->and($cachedRows[0] ?? null)->toBeArray()
        ->and($cachedRows[0]['id'] ?? null)->toBe($hashtag->getKey());
});

it('normalizes wildcard-only global searches to empty results', function (): void {
    User::factory()->create([
        'name' => 'Wildcard Visible User',
        'username' => 'wildcard_visible',
        'is_private' => false,
        'is_banned' => false,
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('search.index', ['type' => 'users', 'q' => '%%__']))
        ->assertOk()
        ->assertSee('No results found')
        ->assertDontSee('Search result: Wildcard Visible User', false);
});

it('applies named throttles to expensive search, catalog, and polling endpoints', function (): void {
    expect(routeMiddleware('search.index'))->toContain('throttle:expensive-search')
        ->and(routeMiddleware('api.breeds.index'))->toContain('throttle:expensive-search')
        ->and(routeMiddleware('comments.gifs.search'))->toContain('throttle:expensive-search')
        ->and(routeMiddleware('marketplace.index'))->toContain('throttle:catalog-browse')
        ->and(routeMiddleware('profile.followers'))->toContain('throttle:catalog-browse')
        ->and(routeMiddleware('notifications.latest'))->toContain('throttle:polling-refresh');
});

it('preloads comment policy context so looped policy checks do not reload posts', function (): void {
    $viewer = User::factory()->create();
    $author = User::factory()->create(['is_private' => false, 'is_banned' => false]);
    $post = Post::factory()->for($author)->create([
        'visibility' => Post::VISIBILITY_PUBLIC,
        'status' => 'published',
    ]);

    Comment::factory()->count(3)->create([
        'post_id' => $post->getKey(),
        'user_id' => $author->getKey(),
    ]);

    $comments = app(CommentService::class)->threadForPost($post, $viewer);

    expect($comments)->toHaveCount(3)
        ->and($comments->every(fn (Comment $comment): bool => $comment->relationLoaded('post')))->toBeTrue()
        ->and($comments->every(fn (Comment $comment): bool => (bool) $comment->getAttribute('policy_can_view_post')))->toBeTrue();

    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    $comments->each(function (Comment $comment) use ($viewer): void {
        Gate::forUser($viewer)->allows('reply', $comment);
        Gate::forUser($viewer)->allows('react', $comment);
        Gate::forUser($viewer)->allows('report', $comment);
    });

    expect(collect($queries)->filter(fn (string $sql): bool => str_contains($sql, 'from "posts"'))->values()->all())
        ->toBeEmpty();
});

it('locks sensitive Livewire identifiers mounted from server context', function (): void {
    $viewer = User::factory()->create();
    $target = User::factory()->create();
    $otherTarget = User::factory()->create();
    $post = Post::factory()->for($viewer)->create();
    $otherPost = Post::factory()->for($viewer)->create();

    expect(fn () => Livewire::actingAs($viewer)
        ->test('profile.report-modal', ['profileUserId' => $target->getKey()])
        ->set('profileUserId', $otherTarget->getKey()))
        ->toThrow(CannotUpdateLockedPropertyException::class);

    expect(fn () => Livewire::actingAs($viewer)
        ->test('posts.share-menu', ['post' => $post])
        ->set('postId', $otherPost->getKey()))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

/**
 * @return array<int, string>
 */
function routeMiddleware(string $name): array
{
    return Route::getRoutes()->getByName($name)?->gatherMiddleware() ?? [];
}

function cachedTrendingHashtagRows(): mixed
{
    try {
        return Cache::tags(['feed', 'hashtags'])->get('feed:trending-hashtags');
    } catch (BadMethodCallException) {
        return Cache::get('feed:trending-hashtags');
    }
}
