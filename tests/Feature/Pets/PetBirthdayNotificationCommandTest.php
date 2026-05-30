<?php

use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Models\Pets\PetOwner;
use App\Notifications\Database\Pets\PetBirthdayCoOwnerNotification;
use App\Notifications\Database\Pets\PetBirthdayFollowerNotification;
use App\Services\PetBirthdayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('processes pets with todays birthday', function (): void {
    Notification::fake();
    Carbon::setTestNow(Carbon::parse('2026-05-23 08:00:00'));

    $birthdayOwner = User::factory()->create();
    $otherOwner = User::factory()->create();

    $birthdayPet = Pet::factory()->for($birthdayOwner)->create([
        'name' => 'Birthday Buddy',
        'birth_date' => null,
        'date_of_birth' => '2020-05-23',
    ]);

    Pet::factory()->for($otherOwner)->create([
        'name' => 'Tomorrow Buddy',
        'birth_date' => '2020-05-24',
        'date_of_birth' => null,
    ]);

    $this->artisan('pets:send-birthday-notifications')
        ->assertSuccessful();

    expect(Post::query()
        ->where('pet_id', $birthdayPet->id)
        ->where('system_source', 'pet_birthday')
        ->exists())->toBeTrue();

    Carbon::setTestNow();
});

it('creates the birthday post and notifies followers and co owners', function (): void {
    Notification::fake();
    Carbon::setTestNow(Carbon::parse('2026-05-23 08:00:00'));

    $owner = User::factory()->create();
    $follower = User::factory()->create();
    $mutedFollower = User::factory()->create([
        'notification_preferences' => ['pet_birthdays' => false],
    ]);
    $coOwner = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create([
        'name' => 'Birthday Buddy',
        'birth_date' => null,
        'date_of_birth' => '2020-05-23',
    ]);

    $follower->followPet($pet);
    $mutedFollower->followPet($pet);
    $coOwner->followPet($pet);

    PetOwner::factory()->for($pet)->for($coOwner, 'user')->create([
        'invited_by_user_id' => $owner->id,
        'role' => PetOwner::ROLE_POSTER,
        'accepted_at' => now(),
    ]);

    app(PetBirthdayService::class)->process($pet->id);

    expect(Post::query()
        ->where('user_id', $owner->id)
        ->where('pet_id', $pet->id)
        ->where('is_system_generated', true)
        ->where('system_source', 'pet_birthday')
        ->exists())->toBeTrue();

    Notification::assertSentTo($follower, PetBirthdayFollowerNotification::class);
    Notification::assertSentTo($coOwner, PetBirthdayCoOwnerNotification::class);
    Notification::assertNotSentTo($mutedFollower, PetBirthdayFollowerNotification::class);
    Notification::assertNothingSentTo($owner);

    Carbon::setTestNow();
});

it('batches co owner birthday follower checks without per-user exists queries', function (): void {
    Notification::fake();
    Carbon::setTestNow(Carbon::parse('2026-05-23 08:00:00'));

    $owner = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create([
        'name' => 'Birthday Buddy',
        'birth_date' => null,
        'date_of_birth' => '2020-05-23',
    ]);
    $coOwners = User::factory()->count(6)->create();

    $coOwners->each(function (User $coOwner) use ($owner, $pet): void {
        $coOwner->followPet($pet);

        PetOwner::factory()->for($pet)->for($coOwner, 'user')->create([
            'invited_by_user_id' => $owner->id,
            'role' => PetOwner::ROLE_POSTER,
            'accepted_at' => now(),
        ]);
    });

    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    app(PetBirthdayService::class)->process($pet->id);

    $perUserFollowerExistsQueries = collect($queries)
        ->filter(fn (string $sql): bool => preg_match('/select\s+exists\s*\(.*pet_followers/is', $sql) === 1)
        ->values();

    expect($perUserFollowerExistsQueries)->toBeEmpty('Per-user pet follower exists queries: '.json_encode($perUserFollowerExistsQueries->all()));
    Notification::assertSentTo($coOwners->all(), PetBirthdayCoOwnerNotification::class);

    Carbon::setTestNow();
});

it('stores and uses an indexed birthday lookup key', function (): void {
    $pet = Pet::factory()->create([
        'birth_date' => '2020-05-23',
        'date_of_birth' => null,
    ]);

    expect($pet->fresh()->birthday_month_day)->toBe('05-23');

    $plan = collect(DB::select(
        'EXPLAIN QUERY PLAN SELECT * FROM pets WHERE birthday_month_day = ? AND is_archived = 0 AND deleted_at IS NULL',
        ['05-23'],
    ))->pluck('detail')->implode(' ');

    expect($plan)->toContain('pets_birthday_archived_lookup_index');
});
