<?php

use App\Jobs\SendDailyReactionSummaryJob;
use App\Jobs\SendReactionNotificationJob;
use App\Mail\DailyReactionSummaryMail;
use App\Models\Content\Post;
use App\Models\Content\PostReactionSnapshot;
use App\Models\Content\Reaction;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Services\PetReactionLeaderboardService;
use App\Services\ReactionSummaryCache;
use App\Services\ReactionVelocityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('caches rendered reaction summary html and invalidates when the top composition changes', function (): void {
    $post = Post::factory()->create([
        'reactions_count' => 3,
        'paw_count' => 2,
        'love_count' => 1,
    ]);

    $summary = app(ReactionSummaryCache::class);
    $cacheKey = "posts:{$post->getKey()}:reaction-summary-html:v1";

    expect((string) $summary->html($post))->toContain('🐾');

    Cache::put($cacheKey, 'stale-stack', now()->addMinute());
    expect((string) $summary->html($post))->toBe('stale-stack');

    $before = Reaction::topCountsForModel($post, 3);

    $post->forceFill([
        'paw_count' => 0,
        'love_count' => 4,
        'reactions_count' => 4,
    ])->save();

    $post->refresh();

    $summary->forgetIfCompositionChanged($post, $before, Reaction::topCountsForModel($post, 3));

    expect(Cache::missing($cacheKey))->toBeTrue()
        ->and((string) $summary->html($post))->toContain('❤️');
});

it('marks posts as trending when reaction velocity exceeds ten per minute', function (): void {
    $post = Post::factory()->create([
        'reactions_count' => 60,
    ]);

    PostReactionSnapshot::factory()->create([
        'post_id' => $post->getKey(),
        'captured_at' => now()->subMinutes(5)->startOfMinute(),
        'reactions_count' => 5,
    ]);

    expect(app(ReactionVelocityService::class)->isTrending($post))->toBeTrue();

    Cache::flush();

    $post->forceFill(['reactions_count' => 50])->save();

    expect(app(ReactionVelocityService::class)->isTrending($post->refresh()))->toBeFalse();
});

it('does not send the delayed reaction notification when the reaction is undone before the window closes', function (): void {
    Notification::fake();

    $reactor = User::factory()->create();
    $author = User::factory()->create();
    $post = Post::factory()->for($author)->create([
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);

    $reaction = Reaction::query()->create([
        'reactable_type' => (new Post)->getMorphClass(),
        'reactable_id' => $post->getKey(),
        'user_id' => $reactor->getKey(),
        'type' => Reaction::TYPE_LOVE,
    ]);

    $reaction->delete();

    (new SendReactionNotificationJob((int) $post->getKey(), (int) $reactor->getKey(), Reaction::TYPE_LOVE))->handle();

    Notification::assertNothingSent();
});

it('dispatches daily reaction summary jobs only for opted-in users', function (): void {
    Queue::fake();

    $optedIn = User::factory()->create([
        'notification_preferences' => [
            'daily_reaction_summary' => true,
        ],
        'timezone' => 'UTC',
    ]);

    User::factory()->create([
        'notification_preferences' => [
            'daily_reaction_summary' => false,
        ],
        'timezone' => 'UTC',
    ]);

    $this->artisan('reactions:send-daily-summaries', ['--force' => true])
        ->assertSuccessful();

    Queue::assertPushed(SendDailyReactionSummaryJob::class, function (SendDailyReactionSummaryJob $job) use ($optedIn): bool {
        return $job->userId === (int) $optedIn->getKey();
    });

    Queue::assertPushed(SendDailyReactionSummaryJob::class, 1);
});

it('queues the daily reaction summary email for heavy reactors', function (): void {
    Mail::fake();

    $user = User::factory()->create([
        'notification_preferences' => [
            'daily_reaction_summary' => true,
        ],
        'timezone' => 'UTC',
    ]);

    foreach (range(1, 21) as $index) {
        $post = Post::factory()->create([
            'reactions_count' => $index,
            'comments_count' => $index % 3,
            'shares_count' => $index % 2,
        ]);

        Reaction::query()->create([
            'reactable_type' => (new Post)->getMorphClass(),
            'reactable_id' => $post->getKey(),
            'user_id' => $user->getKey(),
            'type' => Reaction::TYPE_PAW,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    (new SendDailyReactionSummaryJob((int) $user->getKey(), now('UTC')->toDateString()))->handle();

    Mail::assertQueued(DailyReactionSummaryMail::class, function (DailyReactionSummaryMail $mail) use ($user): bool {
        return $mail->hasTo($user->email)
            && $mail->posts->count() === 5;
    });
});

it('returns the three most loved posts tagged to a pet', function (): void {
    $pet = Pet::factory()->create();
    $posts = collect([3, 20, 8, 11])->map(function (int $count) use ($pet): Post {
        $post = Post::factory()->create([
            'reactions_count' => $count,
            'visibility' => Post::VISIBILITY_PUBLIC,
        ]);

        $post->pets()->attach($pet->getKey());

        return $post;
    });

    $leaderboard = app(PetReactionLeaderboardService::class)->mostLovedPosts($pet);

    expect($leaderboard)->toHaveCount(3)
        ->and($leaderboard->pluck('id')->all())->toBe([
            $posts[1]->getKey(),
            $posts[3]->getKey(),
            $posts[2]->getKey(),
        ]);
});

it('renders the pet reaction leaderboard on the pet about tab', function (): void {
    $owner = User::factory()->create();
    $pet = Pet::factory()->create([
        'user_id' => $owner->getKey(),
        'visibility' => 'public',
        'is_public' => true,
    ]);
    $post = Post::factory()->for($owner)->create([
        'body' => 'Luna discovered a new favorite sunny spot.',
        'reactions_count' => 12,
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);

    $post->pets()->attach($pet->getKey());

    $this->actingAs($owner)
        ->get(route('pets.show', $pet).'?tab=about')
        ->assertOk()
        ->assertSeeText('Most loved posts')
        ->assertSeeText('Luna discovered a new favorite sunny spot.');
});
