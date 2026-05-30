<?php

use App\Jobs\GenerateProfileWrappedImage;
use App\Models\Analytics\ProfileWrappedSummary;
use App\Models\Content\Comment;
use App\Models\Content\Post;
use App\Models\Content\Reaction;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Models\Social\Follow;
use App\Services\ProfileWrappedService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

if (! function_exists('wrappedReaction')) {
    function wrappedReaction(Post $post, User $reactor, string $type, string $createdAt): void
    {
        Reaction::query()->forceCreate([
            'user_id' => $reactor->id,
            'reactable_type' => $post->getMorphClass(),
            'reactable_id' => $post->id,
            'type' => $type,
            'created_at' => Carbon::parse($createdAt),
            'updated_at' => Carbon::parse($createdAt),
        ]);
    }
}

it('generates annual wrapped metrics from profile activity', function (): void {
    $owner = User::factory()->create(['username' => 'wrapped_owner']);
    $firstFan = User::factory()->create();
    $secondFan = User::factory()->create();
    $thirdFan = User::factory()->create();
    $fourthFan = User::factory()->create();

    $marchPost = Post::factory()->for($owner)->create([
        'body' => 'March post that did well',
        'published_at' => '2025-03-10 10:00:00',
        'created_at' => '2025-03-10 10:00:00',
    ]);
    $topPost = Post::factory()->for($owner)->create([
        'body' => 'This was the most engaged wrapped post',
        'published_at' => '2025-03-20 10:00:00',
        'created_at' => '2025-03-20 10:00:00',
    ]);
    Post::factory()->for($owner)->create([
        'published_at' => '2025-08-01 10:00:00',
        'created_at' => '2025-08-01 10:00:00',
    ]);
    Post::factory()->for($owner)->create([
        'published_at' => '2024-12-31 10:00:00',
        'created_at' => '2024-12-31 10:00:00',
    ]);

    wrappedReaction($marchPost, $firstFan, Reaction::TYPE_HAHA, '2025-04-01 10:00:00');
    wrappedReaction($marchPost, $secondFan, Reaction::TYPE_LOVE, '2025-04-02 10:00:00');
    wrappedReaction($topPost, $firstFan, Reaction::TYPE_HAHA, '2025-04-03 10:00:00');
    wrappedReaction($topPost, $secondFan, Reaction::TYPE_HAHA, '2025-04-04 10:00:00');
    wrappedReaction($topPost, $thirdFan, Reaction::TYPE_PAW, '2025-04-05 10:00:00');

    Comment::factory()->for($topPost, 'post')->for($firstFan, 'user')->create(['created_at' => '2025-04-06 10:00:00']);
    Comment::factory()->for($topPost, 'post')->for($secondFan, 'user')->create(['created_at' => '2025-04-07 10:00:00']);
    Comment::factory()->for($topPost, 'post')->for($thirdFan, 'user')->create(['created_at' => '2025-04-08 10:00:00']);
    Comment::factory()->for($marchPost, 'post')->for($fourthFan, 'user')->create(['created_at' => '2025-04-09 10:00:00']);
    Comment::factory()->for($marchPost, 'post')->for($fourthFan, 'user')->create(['created_at' => '2024-04-09 10:00:00']);

    Follow::factory()->create([
        'follower_id' => $firstFan->id,
        'following_id' => $owner->id,
        'status' => 'accepted',
        'created_at' => '2025-02-01 10:00:00',
    ]);
    Follow::factory()->create([
        'follower_id' => $secondFan->id,
        'following_id' => $owner->id,
        'status' => 'accepted',
        'created_at' => '2025-02-02 10:00:00',
    ]);
    Follow::factory()->create([
        'follower_id' => $thirdFan->id,
        'following_id' => $owner->id,
        'status' => 'pending',
        'created_at' => '2025-02-03 10:00:00',
    ]);
    Follow::factory()->create([
        'follower_id' => $fourthFan->id,
        'following_id' => $owner->id,
        'status' => 'accepted',
        'created_at' => '2024-02-03 10:00:00',
    ]);

    Pet::factory()->for($owner)->create(['created_at' => '2025-01-15 10:00:00']);
    Pet::factory()->for($owner)->create(['created_at' => '2025-06-15 10:00:00']);
    Pet::factory()->for($owner)->create(['created_at' => '2024-06-15 10:00:00']);

    $summary = app(ProfileWrappedService::class)->generateForUser($owner, 2025);

    expect($summary->total_posts_published)->toBe(3)
        ->and($summary->total_reactions_received)->toBe(5)
        ->and($summary->top_reaction_type)->toBe(Reaction::TYPE_HAHA)
        ->and($summary->top_reaction_count)->toBe(3)
        ->and($summary->most_active_month)->toBe(3)
        ->and($summary->most_active_month_posts)->toBe(2)
        ->and($summary->new_followers_count)->toBe(2)
        ->and($summary->pets_added_count)->toBe(2)
        ->and($summary->most_engaged_post_id)->toBe($topPost->id)
        ->and($summary->most_engaged_post_score)->toBe(6);
});

it('queues share image generation when the wrapped command runs', function (): void {
    Queue::fake();

    $owner = User::factory()->create(['username' => 'wrapped_command_owner']);
    Post::factory()->for($owner)->create([
        'published_at' => '2025-01-10 10:00:00',
        'created_at' => '2025-01-10 10:00:00',
    ]);

    $this->artisan('profiles:wrapped-generate', ['--year' => 2025])
        ->assertExitCode(0);

    $this->assertDatabaseHas('profile_wrapped_summaries', [
        'user_id' => $owner->id,
        'year' => 2025,
        'total_posts_published' => 1,
    ]);

    Queue::assertPushed(GenerateProfileWrappedImage::class, function (GenerateProfileWrappedImage $job) use ($owner): bool {
        $summary = ProfileWrappedSummary::query()->find($job->summaryId);

        return $summary instanceof ProfileWrappedSummary && (int) $summary->user_id === (int) $owner->id;
    });
});

it('writes a public png for a wrapped summary', function (): void {
    if (! extension_loaded('gd')) {
        $this->markTestSkipped('GD extension is required to generate profile wrapped images.');
    }

    Storage::fake('public');

    $owner = User::factory()->create(['username' => 'wrapped_image_owner']);
    $summary = ProfileWrappedSummary::factory()->for($owner, 'user')->create([
        'year' => 2025,
        'total_posts_published' => 9,
        'total_reactions_received' => 42,
        'top_reaction_type' => Reaction::TYPE_LOVE,
        'top_reaction_count' => 14,
        'most_active_month' => 5,
        'most_active_month_posts' => 4,
        'new_followers_count' => 7,
        'pets_added_count' => 1,
        'most_engaged_post_score' => 18,
    ]);

    (new GenerateProfileWrappedImage((int) $summary->id))->handle(app(ProfileWrappedService::class));

    $summary->refresh();

    expect($summary->share_image_path)->toBe('profile-wrapped/2025/user-'.$owner->id.'.png')
        ->and($summary->share_image_generated_at)->not()->toBeNull();

    Storage::disk('public')->assertExists($summary->share_image_path);

    $contents = Storage::disk('public')->get($summary->share_image_path);

    expect(substr($contents, 0, 8))->toBe("\x89PNG\r\n\x1a\n");
});

it('shows wrapped only to the profile owner during the first two weeks of january', function (): void {
    Carbon::setTestNow(Carbon::parse('2027-01-08 10:00:00'));

    try {
        $owner = User::factory()->create([
            'name' => 'Wrapped Owner',
            'username' => 'wrapped_visible_owner',
            'timezone' => 'Europe/Vilnius',
        ]);
        $visitor = User::factory()->create();

        ProfileWrappedSummary::factory()->for($owner, 'user')->create([
            'year' => 2026,
            'total_posts_published' => 12,
            'total_reactions_received' => 99,
            'top_reaction_type' => Reaction::TYPE_HAHA,
            'top_reaction_count' => 35,
            'most_active_month' => 4,
            'most_active_month_posts' => 6,
            'new_followers_count' => 15,
            'pets_added_count' => 2,
            'most_engaged_post_score' => 44,
            'share_image_path' => 'profile-wrapped/2026/user-'.$owner->id.'.png',
        ]);

        $this->actingAs($owner)
            ->get(route('profile.show', ['user' => $owner]))
            ->assertOk()
            ->assertSee('data-ui="profile-wrapped-card"', false)
            ->assertSeeText('2026 Profile wrapped')
            ->assertSeeText('12')
            ->assertSeeText('Reactions received')
            ->assertSeeText('Haha')
            ->assertSeeText('April')
            ->assertSee('data-ui="profile-wrapped-share-image"', false);

        $this->actingAs($visitor)
            ->get(route('profile.show', ['user' => $owner]))
            ->assertOk()
            ->assertDontSee('data-ui="profile-wrapped-card"', false)
            ->assertDontSeeText('Profile wrapped');

        Carbon::setTestNow(Carbon::parse('2027-01-15 10:00:00'));

        $this->actingAs($owner)
            ->get(route('profile.show', ['user' => $owner]))
            ->assertOk()
            ->assertDontSee('data-ui="profile-wrapped-card"', false)
            ->assertDontSeeText('Profile wrapped');
    } finally {
        Carbon::setTestNow();
    }
});
