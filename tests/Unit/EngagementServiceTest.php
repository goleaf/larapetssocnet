<?php

use App\Models\Post;
use App\Models\Report;
use App\Models\Share;
use App\Models\User;
use App\Services\ProfilePostOrderingService;
use App\Services\ReactionService;
use App\Services\ReportService;
use App\Services\SavedPostService;
use App\Services\ShareService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('toggles reactions via ReactionService', function (): void {
    $user = User::factory()->create();
    $post = Post::factory()->create([
        'user_id' => User::factory()->create()->id,
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);

    $service = app(ReactionService::class);

    $first = $service->react($user, $post, 'love');
    expect($first['action'])->toBe('added');
    expect($post->refresh()->likes_count)->toBe(1);

    $second = $service->react($user, $post, 'love');
    expect($second['action'])->toBe('removed');
    expect($post->refresh()->likes_count)->toBe(0);
});

it('toggles saved posts via SavedPostService', function (): void {
    $user = User::factory()->create();
    $post = Post::factory()->create([
        'user_id' => User::factory()->create()->id,
    ]);

    $service = app(SavedPostService::class);

    expect($service->toggle($user, $post))->toBeTrue();
    expect($post->refresh()->save_count)->toBe(1);

    expect($service->toggle($user, $post))->toBeFalse();
    expect($post->refresh()->save_count)->toBe(0);
});

it('dedupes reports by reporter + reportable + reason', function (): void {
    $reporter = User::factory()->create();
    $author = User::factory()->create();
    $post = Post::factory()->for($author)->create();

    $service = app(ReportService::class);

    $service->create($reporter, $post, Report::REASON_SPAM, 'Spam');
    $service->create($reporter, $post, Report::REASON_SPAM, 'Spam again');

    expect(Report::query()->count())->toBe(1);
});

it('tracks shares idempotently per user', function (): void {
    $user = User::factory()->create();
    $post = Post::factory()->create([
        'user_id' => User::factory()->create()->id,
    ]);

    $service = app(ShareService::class);

    $first = $service->track($user, $post, 'copy_link');
    expect($first['shared'])->toBeTrue();

    $second = $service->track($user, $post, 'copy_link');
    expect($second['shared'])->toBeFalse();

    expect(Share::query()->count())->toBe(1);
});

it('orders pinned posts first for profile queries', function (): void {
    $user = User::factory()->create();
    $pinned = Post::factory()->for($user)->create(['is_pinned' => true, 'pinned_at' => now()->subHour()]);
    $regular = Post::factory()->for($user)->create(['is_pinned' => false]);

    $service = app(ProfilePostOrderingService::class);

    $ordered = $service->apply(Post::query()->forProfile($user))->get();

    expect($ordered->first()->id)->toBe($pinned->id);
    expect($ordered->last()->id)->toBe($regular->id);
});
