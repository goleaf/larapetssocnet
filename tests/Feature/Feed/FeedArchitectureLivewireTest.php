<?php

use App\Models\Content\Post;
use App\Models\Identity\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('routes the feed to a class based full-page livewire component', function (): void {
    $route = Route::getRoutes()->getByName('feed.index');

    expect($route)->not->toBeNull()
        ->and($route?->getAction('livewire_component'))->toBe('pages.feed.index')
        ->and(is_file(app_path('Livewire/Pages/Feed/Index.php')))->toBeTrue()
        ->and(is_file(resource_path('views/livewire/pages/feed/index.blade.php')))->toBeTrue()
        ->and(is_file(resource_path('views/components/pages/feed/⚡index.blade.php')))->toBeFalse();
});

it('keeps the migrated feed page blade template free of inline livewire php', function (): void {
    $source = file_get_contents(resource_path('views/livewire/pages/feed/index.blade.php'));

    expect($source)->toBeString()
        ->and((string) $source)->not->toContain('<?php')
        ->and((string) $source)->not->toContain('new class extends Component');
});

it('renders the feed page component with minimal locked public state', function (): void {
    $viewer = User::factory()->create();

    Livewire::actingAs($viewer)
        ->test('pages.feed.index')
        ->assertSet('source', '')
        ->assertSet('type', '')
        ->assertSee('data-ui="feed-livewire-page"', false);
});

it('rejects feed page filter tampering outside the child stream component', function (): void {
    $viewer = User::factory()->create();

    expect(fn () => Livewire::actingAs($viewer)
        ->test('pages.feed.index')
        ->set('source', 'people'))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

it('renders the feed as a full-page livewire shell with bundled lazy sidebars and eager center stream', function (): void {
    $viewer = User::factory()->create();

    $this->actingAs($viewer)
        ->get(route('feed.index'))
        ->assertOk()
        ->assertSee('wire:name="pages.feed.index"', false)
        ->assertSee('data-ui="feed-livewire-page"', false)
        ->assertSee('data-ui="feed-stream"', false)
        ->assertSee('data-ui="feed-left-sidebar-skeleton"', false)
        ->assertSee('data-ui="feed-right-sidebar-skeleton"', false)
        ->assertDontSee('data-ui="feed-stream-skeleton"', false);
});

it('builds the main feed eligibility query from precomputed feed items with relationship fallback', function (): void {
    $viewer = User::factory()->create();

    $sql = strtolower(Post::query()->forFeed((int) $viewer->getKey())->toSql());

    expect($sql)
        ->toContain('feed_items')
        ->toContain('union')
        ->toContain('feed_post_ids')
        ->toContain('pet_followers')
        ->toContain('follows')
        ->toContain('posts"."visibility');
});

it('renders feed post cards as independent livewire components', function (): void {
    $viewer = User::factory()->create();

    Post::factory()->for($viewer)->create([
        'body' => 'island-post-card-body',
    ]);

    $this->actingAs($viewer)
        ->get(route('feed.index'))
        ->assertOk()
        ->assertSee('wire:name="posts.card"', false)
        ->assertSee('data-ui="feed-post-livewire-card"', false);
});
