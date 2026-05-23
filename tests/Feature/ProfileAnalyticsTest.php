<?php

use App\Http\Controllers\Profile\PublicProfileController;
use App\Jobs\RecordProfileView;
use App\Models\Analytics\ProfileView;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\View\View;

uses(RefreshDatabase::class);

it('records one authenticated profile view per viewer per day', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-05-17 12:00:00'));

    try {
        $owner = User::factory()->create([
            'username' => 'profile_owner',
            'timezone' => 'Europe/Vilnius',
        ]);
        $viewer = User::factory()->create();

        $this->actingAs($viewer)
            ->get(route('profile.show', ['user' => $owner]))
            ->assertOk();

        $this->actingAs($viewer)
            ->get(route('profile.show', ['user' => $owner]))
            ->assertOk();

        $this->assertDatabaseCount('profile_views', 1);
        $this->assertDatabaseHas('profile_views', [
            'profile_user_id' => $owner->id,
            'viewer_user_id' => $viewer->id,
            'viewed_on' => '2026-05-17',
        ]);
    } finally {
        Carbon::setTestNow();
    }
});

it('dispatches a queued profile view recorder for authenticated visitors only', function (): void {
    Queue::fake();

    $owner = User::factory()->create([
        'username' => 'queued_view_owner',
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);
    $viewer = User::factory()->create();

    $this->get(route('profile.show', ['user' => $owner]))
        ->assertOk();

    Queue::assertNotPushed(RecordProfileView::class);

    Queue::fake();

    $this->actingAs($viewer)
        ->get(route('profile.show', ['user' => $owner]))
        ->assertOk();

    Queue::assertPushed(RecordProfileView::class, fn (RecordProfileView $job): bool => $job->profileUserId === $owner->id
        && $job->viewerUserId === $viewer->id);

    Queue::fake();

    $this->actingAs($owner)
        ->get(route('profile.show', ['user' => $owner]))
        ->assertOk();

    Queue::assertNotPushed(RecordProfileView::class);
});

it('does not touch an existing daily profile view when the same viewer returns', function (): void {
    $owner = User::factory()->create([
        'username' => 'same_day_view_owner',
        'timezone' => 'Europe/Vilnius',
    ]);
    $viewer = User::factory()->create();

    Carbon::setTestNow(Carbon::parse('2026-05-17 09:00:00'));

    try {
        (new RecordProfileView((int) $owner->id, (int) $viewer->id))->handle();

        /** @var ProfileView $firstView */
        $firstView = ProfileView::query()->firstOrFail();
        $originalUpdatedAt = $firstView->updated_at?->toDateTimeString();

        Carbon::setTestNow(Carbon::parse('2026-05-17 18:00:00'));

        (new RecordProfileView((int) $owner->id, (int) $viewer->id))->handle();

        $this->assertDatabaseCount('profile_views', 1);

        /** @var ProfileView $sameView */
        $sameView = ProfileView::query()->firstOrFail();

        expect($sameView->updated_at?->toDateTimeString())->toBe($originalUpdatedAt)
            ->and($sameView->viewed_on?->toDateString())->toBe('2026-05-17');
    } finally {
        Carbon::setTestNow();
    }
});

it('uses the profile owner timezone to determine the daily view boundary', function (): void {
    $owner = User::factory()->create([
        'username' => 'timezone_view_owner',
        'timezone' => 'America/Los_Angeles',
    ]);
    $viewer = User::factory()->create();

    try {
        Carbon::setTestNow(Carbon::parse('2026-05-18 06:30:00', 'UTC'));
        (new RecordProfileView((int) $owner->id, (int) $viewer->id))->handle();

        Carbon::setTestNow(Carbon::parse('2026-05-18 08:30:00', 'UTC'));
        (new RecordProfileView((int) $owner->id, (int) $viewer->id))->handle();

        $this->assertDatabaseCount('profile_views', 2);
        $this->assertDatabaseHas('profile_views', [
            'profile_user_id' => $owner->id,
            'viewer_user_id' => $viewer->id,
            'viewed_on' => '2026-05-17',
        ]);
        $this->assertDatabaseHas('profile_views', [
            'profile_user_id' => $owner->id,
            'viewer_user_id' => $viewer->id,
            'viewed_on' => '2026-05-18',
        ]);
    } finally {
        Carbon::setTestNow();
    }
});

it('shows profile view analytics only to the profile owner', function (): void {
    $owner = User::factory()->create(['username' => 'analytics_owner']);
    $viewer = User::factory()->create();

    ProfileView::query()->create([
        'profile_user_id' => $owner->id,
        'viewer_user_id' => $viewer->id,
        'viewed_on' => now()->toDateString(),
    ]);

    expect(ProfileView::uniqueViewerCountForProfile(
        $owner,
        now()->subDays(ProfileView::RECENT_UNIQUE_VIEWER_DAYS - 1)->toDateString(),
        now()->toDateString(),
    ))->toBe(1);

    $this->actingAs($owner)
        ->get(route('profile.show', ['user' => $owner]))
        ->assertOk()
        ->assertSee('data-ui="profile-view-analytics"', false)
        ->assertSee('data-ui="profile-view-analytics-note"', false)
        ->assertSeeText('1 profile visit in the last 30 days')
        ->assertSeeText('Only you can see this.');

    $this->actingAs($viewer)
        ->get(route('profile.show', ['user' => $owner]))
        ->assertOk()
        ->assertDontSee('profile visit in the last 30 days')
        ->assertDontSee('Only you can see this.')
        ->assertDontSee('data-ui="profile-view-analytics"', false);
});

it('does not fetch owner profile view analytics for visitor renders', function (): void {
    Queue::fake([RecordProfileView::class]);

    $owner = User::factory()->create(['username' => 'visitor_analytics_owner']);
    $viewer = User::factory()->create();

    ProfileView::query()->create([
        'profile_user_id' => $owner->id,
        'viewer_user_id' => $viewer->id,
        'viewed_on' => now()->toDateString(),
    ]);

    DB::flushQueryLog();
    DB::enableQueryLog();

    try {
        $response = $this->actingAs($viewer)
            ->get(route('profile.show', ['user' => $owner]));

        $queries = DB::getQueryLog();
    } finally {
        DB::disableQueryLog();
    }

    $response
        ->assertOk()
        ->assertDontSee('profile visit in the last 30 days')
        ->assertDontSee('data-ui="profile-view-analytics"', false);

    $profileViewAggregateQueries = collect($queries)
        ->pluck('query')
        ->map(fn (string $query): string => strtolower($query))
        ->filter(fn (string $query): bool => str_contains($query, 'from "profile_views"') && str_contains($query, 'count('))
        ->values();

    expect($profileViewAggregateQueries)->toBeEmpty();

    Queue::assertPushed(RecordProfileView::class, fn (RecordProfileView $job): bool => $job->profileUserId === $owner->id
        && $job->viewerUserId === $viewer->id);
});

it('counts unique profile viewers across the owner local last 30 days', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-05-23 12:00:00'));

    try {
        $owner = User::factory()->create([
            'username' => 'unique_view_owner',
            'timezone' => 'Europe/Vilnius',
        ]);
        $firstViewer = User::factory()->create();
        $secondViewer = User::factory()->create();
        $oldViewer = User::factory()->create();

        ProfileView::query()->create([
            'profile_user_id' => $owner->id,
            'viewer_user_id' => $firstViewer->id,
            'viewed_on' => now()->toDateString(),
        ]);
        ProfileView::query()->create([
            'profile_user_id' => $owner->id,
            'viewer_user_id' => $firstViewer->id,
            'viewed_on' => now()->subDay()->toDateString(),
        ]);
        ProfileView::query()->create([
            'profile_user_id' => $owner->id,
            'viewer_user_id' => $secondViewer->id,
            'viewed_on' => now()->subDays(29)->toDateString(),
        ]);
        ProfileView::query()->create([
            'profile_user_id' => $owner->id,
            'viewer_user_id' => $oldViewer->id,
            'viewed_on' => now()->subDays(30)->toDateString(),
        ]);
        ProfileView::query()->create([
            'profile_user_id' => $owner->id,
            'viewer_user_id' => $owner->id,
            'viewed_on' => now()->toDateString(),
        ]);

        expect(ProfileView::uniqueViewerCountForProfile(
            $owner,
            now()->subDays(29)->toDateString(),
            now()->toDateString(),
        ))->toBe(2);

        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $response = $this->actingAs($owner)
                ->get(route('profile.show', ['user' => $owner]));

            $queries = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
        }

        $response
            ->assertOk()
            ->assertSee('2 profile visits in the last 30 days')
            ->assertDontSee('3 profile visits in the last 30 days');

        $profileViewAggregateQueries = collect($queries)
            ->pluck('query')
            ->map(fn (string $query): string => strtolower($query))
            ->filter(fn (string $query): bool => str_contains($query, 'from "profile_views"') && str_contains($query, 'count('))
            ->values();

        expect($profileViewAggregateQueries)->toHaveCount(1);
    } finally {
        Carbon::setTestNow();
    }
});

it('calculates profile completeness and missing items from profile fields', function (): void {
    $user = User::factory()->create([
        'avatar_path' => null,
        'cover_photo_path' => null,
        'profile_photo_path' => null,
        'bio' => 'Too short',
        'location' => null,
        'city' => null,
        'website' => null,
        'birth_date' => null,
        'pets_count' => 0,
        'following_count' => 0,
    ]);

    expect($user->profile_completeness_percentage)->toBe(0)
        ->and(collect($user->profile_completeness_missing_items)->pluck('key')->all())
        ->toContain('avatar', 'cover', 'bio', 'location', 'website', 'birth_date', 'pets', 'following');

    $user->forceFill([
        'avatar_path' => 'https://example.test/avatar.jpg',
        'cover_photo_path' => 'https://example.test/cover.jpg',
        'bio' => 'A complete profile bio with enough useful detail.',
        'location' => 'Vilnius',
        'website' => 'https://prus.dev',
        'birth_date' => '1992-04-01',
        'pets_count' => 1,
        'following_count' => 5,
    ])->save();

    $user->refresh();

    expect($user->profile_completeness_percentage)->toBe(100)
        ->and($user->profile_completeness_missing_items)->toBe([]);
});

it('calculates profile completeness percentages for varied field combinations', function (array $attributes, int $expectedPercentage, array $expectedMissing): void {
    $user = User::factory()->create([
        'avatar_path' => null,
        'cover_photo_path' => null,
        'profile_photo_path' => null,
        'bio' => null,
        'location' => null,
        'city' => null,
        'website' => null,
        'birth_date' => null,
        'pets_count' => 0,
        'following_count' => 0,
    ]);

    $user->forceFill($attributes)->save();
    $user->refresh();

    expect($user->profile_completeness_percentage)->toBe($expectedPercentage)
        ->and(collect($user->profile_completeness_missing_items)->pluck('key')->all())
        ->toEqual($expectedMissing);
})->with([
    'empty profile' => [
        [],
        0,
        ['avatar', 'cover', 'bio', 'location', 'website', 'birth_date', 'pets', 'following'],
    ],
    'avatar bio and location' => [
        [
            'avatar_path' => 'https://example.test/avatar.jpg',
            'bio' => 'A partial profile bio with enough detail.',
            'city' => 'Vilnius',
        ],
        40,
        ['cover', 'website', 'birth_date', 'pets', 'following'],
    ],
    'contact and date fields' => [
        [
            'website' => 'https://example.test',
            'birth_date' => '1994-05-20',
        ],
        20,
        ['avatar', 'cover', 'bio', 'location', 'pets', 'following'],
    ],
    'pet and following requirements' => [
        [
            'pets_count' => 1,
            'following_count' => 5,
        ],
        25,
        ['avatar', 'cover', 'bio', 'location', 'website', 'birth_date'],
    ],
    'complete profile' => [
        [
            'avatar_path' => 'https://example.test/avatar.jpg',
            'cover_photo_path' => 'https://example.test/cover.jpg',
            'bio' => 'A complete profile bio with enough useful detail.',
            'location' => 'Vilnius',
            'website' => 'https://example.test',
            'birth_date' => '1994-05-20',
            'pets_count' => 1,
            'following_count' => 5,
        ],
        100,
        [],
    ],
]);

it('recalculates owner profile completeness fresh on each profile page load', function (): void {
    $owner = User::factory()->create([
        'username' => 'fresh_completion_owner',
        'avatar_path' => 'https://example.test/avatar.jpg',
        'cover_photo_path' => 'https://example.test/cover.jpg',
        'bio' => 'A complete profile bio with enough useful detail.',
        'location' => 'Vilnius',
        'website' => 'https://example.test',
        'birth_date' => '1994-05-20',
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    $this->actingAs($owner)
        ->get(route('profile.show', ['user' => $owner]))
        ->assertOk()
        ->assertSee('aria-valuenow="75"', false)
        ->assertSee('75%');

    Pet::factory()
        ->for($owner)
        ->create();

    User::factory()
        ->count(5)
        ->create()
        ->each(fn (User $followedUser): string => $owner->follow($followedUser));

    $this->actingAs($owner)
        ->get(route('profile.show', ['user' => $owner]))
        ->assertOk()
        ->assertSee('data-ui="profile-completeness-complete-card"', false)
        ->assertSee('Your profile is complete!')
        ->assertDontSee('data-ui="profile-completeness-progress"', false);

    expect($owner->refresh()->profile_completed_at)->not->toBeNull();
});

it('calculates profile completeness from a narrow summary query', function (): void {
    $user = User::factory()->create([
        'avatar_path' => null,
        'cover_photo_path' => null,
        'profile_photo_path' => null,
        'bio' => 'Too short',
        'location' => null,
        'city' => null,
        'website' => null,
        'birth_date' => null,
    ]);

    DB::flushQueryLog();
    DB::enableQueryLog();

    try {
        $summary = User::profileCompletenessSummaryFor((int) $user->getKey());
        $queries = DB::getQueryLog();
    } finally {
        DB::disableQueryLog();
    }

    expect($summary['percentage'])->toBe(0)
        ->and(collect($summary['missing_items'])->pluck('key')->all())
        ->toContain('avatar', 'cover', 'bio', 'location', 'website', 'birth_date', 'pets', 'following');

    $profileQueries = collect($queries)
        ->pluck('query')
        ->filter(fn (string $query): bool => str_contains($query, 'from "users"') && str_contains($query, '"bio"'))
        ->values();

    expect($profileQueries)->toHaveCount(1);

    $sql = strtolower((string) $profileQueries->first());

    expect($sql)
        ->toContain('"bio"')
        ->toContain('"website"')
        ->toContain('"birth_date"')
        ->not->toContain('select * from "users"')
        ->not->toContain('select "users".*')
        ->not->toContain('"email"')
        ->not->toContain('"password"');
});

it('loads profile owner media, pets, and follow counts for the profile surface', function (): void {
    $owner = User::factory()->create([
        'username' => 'surface_owner',
        'is_private' => false,
    ]);
    $viewer = User::factory()->create();
    $follower = User::factory()->create();

    Pet::factory()
        ->count(3)
        ->for($owner)
        ->create();

    $follower->follow($owner);

    $response = $this->actingAs($viewer)
        ->get(route('profile.show', ['user' => $owner, 'tab' => 'pets']));

    $response->assertOk();

    $request = Request::create(route('profile.show', ['user' => $owner, 'tab' => 'pets']), 'GET', [
        'tab' => 'pets',
    ]);
    $request->setUserResolver(fn () => $viewer);

    $view = app(PublicProfileController::class)->show($request, $owner);

    expect($view)->toBeInstanceOf(View::class);

    /** @var User $profileUser */
    $profileUser = $view->getData()['profileUser'];
    $pets = $view->getData()['pets'];

    expect($profileUser->relationLoaded('media'))->toBeTrue()
        ->and((int) $profileUser->followers_count)->toBe(1)
        ->and($pets)->toHaveCount(3);

    $pets->each(function (Pet $pet): void {
        expect($pet->relationLoaded('media'))->toBeTrue()
            ->and($pet->relationLoaded('user'))->toBeFalse();
    });
});

it('renders verified profile badge and saved cover focal point', function (): void {
    $user = User::factory()->create([
        'name' => 'Verified Owner',
        'username' => 'verified_owner',
        'cover_photo_path' => 'https://example.test/cover.jpg',
        'cover_photo_position' => 72.5,
        'is_verified' => true,
    ]);

    $this->get(route('profile.show', ['user' => $user]))
        ->assertOk()
        ->assertSee('Verified PetSocial account')
        ->assertSee('This account has been verified by PetSocial')
        ->assertSee('style="object-position: center 72.5%"', false)
        ->assertSee('position: 72.5', false);
});

it('lets profile owners save cover focal point', function (): void {
    $user = User::factory()->create([
        'cover_photo_position' => 50,
    ]);

    $this->actingAs($user)
        ->patchJson(route('profile.cover-position.update'), ['position' => 82.25])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('position', 82.25);

    expect((float) $user->refresh()->cover_photo_position)->toBe(82.25);
});

it('rejects cover focal points outside the supported percentage range', function (float $position): void {
    $user = User::factory()->create([
        'cover_photo_position' => 50,
    ]);

    $this->actingAs($user)
        ->patchJson(route('profile.cover-position.update'), ['position' => $position])
        ->assertInvalid(['position']);

    expect((float) $user->refresh()->cover_photo_position)->toBe(50.0);
})->with([
    'below zero' => -0.01,
    'above one hundred' => 100.01,
]);

it('normalizes direct cover focal point writes before storage', function (float $position, float $expected): void {
    $user = User::factory()->create();

    $user->forceFill(['cover_photo_position' => $position])->saveQuietly();

    expect((float) $user->refresh()->cover_photo_position)->toBe($expected);
})->with([
    'minimum floor' => [-12.5, User::MIN_COVER_PHOTO_POSITION],
    'maximum ceiling' => [140.75, User::MAX_COVER_PHOTO_POSITION],
    'two decimal precision' => [64.987, 64.99],
]);
