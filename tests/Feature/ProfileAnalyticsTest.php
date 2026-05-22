<?php

use App\Http\Controllers\Profile\PublicProfileController;
use App\Models\Analytics\ProfileView;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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

it('shows profile view analytics only to the profile owner', function (): void {
    $owner = User::factory()->create(['username' => 'analytics_owner']);
    $viewer = User::factory()->create();

    ProfileView::query()->create([
        'profile_user_id' => $owner->id,
        'viewer_user_id' => $viewer->id,
        'viewed_on' => now()->toDateString(),
    ]);

    $this->actingAs($owner)
        ->get(route('profile.show', ['user' => $owner]))
        ->assertOk()
        ->assertSee('profile visits in the last 30 days');

    $this->actingAs($viewer)
        ->get(route('profile.show', ['user' => $owner]))
        ->assertOk()
        ->assertDontSee('profile visits in the last 30 days');
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
