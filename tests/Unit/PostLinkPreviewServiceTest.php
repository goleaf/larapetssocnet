<?php

use App\Events\PostLinkPreviewFetched;
use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Services\PostLinkPreviewService;
use App\Services\PostMetadataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

it('stores fetched link metadata on a post and dispatches the fetched event', function (): void {
    Event::fake([PostLinkPreviewFetched::class]);

    $author = User::factory()->create();
    $post = Post::factory()->for($author)->create(['link_preview' => null]);
    $service = new class extends PostMetadataService
    {
        /**
         * @return array{url: string, title: string, description: string, image: string, domain: string}
         */
        public function fetchLinkPreview(string $url): ?array
        {
            return [
                'url' => $url,
                'title' => 'Luna at the park',
                'description' => 'A sunny afternoon walk',
                'image' => 'https://example.com/luna.jpg',
                'domain' => 'example.com',
            ];
        }
    };

    (new PostLinkPreviewService($service))->fetch('https://example.com/luna', (int) $post->getKey());

    expect($post->fresh()->link_preview)->toMatchArray([
        'url' => 'https://example.com/luna',
        'title' => 'Luna at the park',
    ]);

    Event::assertDispatched(PostLinkPreviewFetched::class, fn (PostLinkPreviewFetched $event): bool => $event->postId === $post->id
        && $event->preview['title'] === 'Luna at the park');
});

it('caches composer preview results without requiring a post id', function (): void {
    $service = new class extends PostMetadataService
    {
        /**
         * @return array{url: string, title: string, domain: string}
         */
        public function fetchLinkPreview(string $url): ?array
        {
            return [
                'url' => $url,
                'title' => 'Adoption update',
                'domain' => 'example.com',
            ];
        }
    };

    (new PostLinkPreviewService($service))->fetch(
        url: 'https://example.com/adoption',
        cacheKey: 'tests:link-preview',
    );

    $cached = Cache::get('tests:link-preview');

    expect($cached['status'])->toBe('ready')
        ->and($cached['url'])->toBe('https://example.com/adoption')
        ->and($cached['preview']['title'])->toBe('Adoption update')
        ->and($cached['preview']['domain'])->toBe('example.com');
});
