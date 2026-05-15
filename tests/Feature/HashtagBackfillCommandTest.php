<?php

use App\Models\Content\Hashtag;
use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Services\Maintenance\MaintenanceTaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('backfills hashtags and recounts usage', function (): void {
    $author = User::factory()->create(['is_private' => false]);

    $post = Post::withoutEvents(function () use ($author): Post {
        return Post::factory()->for($author)->create([
            'body' => 'Backfill #cats post',
            'visibility' => Post::VISIBILITY_PUBLIC,
        ]);
    });

    expect($post->hashtags()->count())->toBe(0);

    $result = app(MaintenanceTaskService::class)->backfillPostHashtags(recount: true);

    expect($result->metrics['processed'])->toBe(1);

    $hashtag = Hashtag::query()->where('normalized_name', 'cats')->firstOrFail();

    $this->assertDatabaseHas('post_hashtag', [
        'post_id' => $post->getKey(),
        'hashtag_id' => $hashtag->getKey(),
    ]);

    $hashtag->refresh();
    expect($hashtag->posts_count)->toBe(1);
});
