<?php

use App\Http\Controllers\Profile\PublicProfileController;
use App\Models\Content\Post;
use App\Models\Gamification\Badge;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

uses(RefreshDatabase::class);

if (! function_exists('profileTestPayload')) {
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function profileTestPayload(User $user, array $overrides = []): array
    {
        return array_merge([
            'name' => $user->name,
            'email' => $user->email,
            'username' => $user->username,
            'username_confirm' => $user->username,
            'bio' => $user->bio,
            'location' => $user->location ?? $user->city,
            'website' => 'https://example.test',
        ], $overrides);
    }
}

test('profile settings page is displayed', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk();
});

test('profile information can be updated and email verification resets when email changes', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), profileTestPayload($user, [
            'name' => 'Updated User',
            'email' => 'updated@example.test',
            'username' => 'updated_user',
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Updated User');
    expect($user->email)->toBe('updated@example.test');
    expect($user->username)->toBe('updated_user');
    expect($user->email_verified_at)->toBeNull();
});

test('email verification status is unchanged when email does not change', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), profileTestPayload($user, [
            'name' => 'Same Email User',
            'username' => 'same_email_user',
            'email' => $user->email,
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->email_verified_at)->not()->toBeNull();
});

test('username must be unique when updating profile', function (): void {
    $taken = User::factory()->create(['username' => 'already_taken']);
    $user = User::factory()->create(['username' => 'free_username']);

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), profileTestPayload($user, [
            'username' => $taken->username,
        ]))
        ->assertSessionHasErrors(['username'])
        ->assertRedirect(route('profile.edit'));
});

test('username input is normalized when updating profile', function (): void {
    $user = User::factory()->create(['username' => 'initial_name']);

    $this->actingAs($user)
        ->patch(route('profile.update'), profileTestPayload($user, [
            'username' => '..InVaLiD Name__',
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->username)->toBe('invalidname');
});

test('avatar and cover images can be uploaded', function (): void {
    Storage::fake('public');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), profileTestPayload($user, [
            'avatar' => UploadedFile::fake()->image('avatar.jpg', 640, 640),
            'cover' => UploadedFile::fake()->image('cover.jpg', 1600, 900),
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    $avatarMedia = $user->getFirstMedia('avatar');
    $coverMedia = $user->getFirstMedia('cover');

    expect($avatarMedia)->not()->toBeNull();
    expect($coverMedia)->not()->toBeNull();

    Storage::disk($avatarMedia->disk)->assertExists($avatarMedia->getPathRelativeToRoot());
    Storage::disk($coverMedia->disk)->assertExists($coverMedia->getPathRelativeToRoot());
});

test('avatar and cover images can be removed', function (): void {
    Storage::fake('public');

    $user = User::factory()->create();

    $user->addMedia(UploadedFile::fake()->image('existing-avatar.jpg'))
        ->toMediaCollection('avatar');
    $user->addMedia(UploadedFile::fake()->image('existing-cover.jpg', 1200, 630))
        ->toMediaCollection('cover');

    expect($user->getMedia('avatar'))->toHaveCount(1);
    expect($user->getMedia('cover'))->toHaveCount(1);

    $this->actingAs($user)
        ->patch(route('profile.update'), profileTestPayload($user, [
            'remove_avatar' => true,
            'remove_cover' => true,
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->getMedia('avatar'))->toHaveCount(0);
    expect($user->getMedia('cover'))->toHaveCount(0);
});

test('public profiles are visible to guests and authenticated viewers', function (): void {
    $user = User::factory()->create([
        'name' => 'Public Profile User',
        'username' => 'public_user',
        'is_private' => false,
    ]);

    $this->get(route('profile.show', ['user' => $user]))
        ->assertOk()
        ->assertSee('Public Profile User')
        ->assertSee('@public_user');

    $this->actingAs(User::factory()->create())
        ->get(route('profile.show', ['user' => $user]))
        ->assertOk()
        ->assertSee('Public Profile User')
        ->assertSee('@public_user');
});

test('private profiles hide content from authenticated strangers', function (): void {
    $user = User::factory()->create([
        'username' => 'private_user',
        'is_private' => true,
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('profile.show', ['user' => $user]))
        ->assertOk()
        ->assertSee('This profile is private');
});

test('followers can view private profile pets tab', function (): void {
    $privateUser = User::factory()->create([
        'username' => 'private_owner',
        'is_private' => true,
    ]);

    Pet::factory()->for($privateUser)->create([
        'name' => 'Milo',
        'species' => 'cat',
    ]);

    $follower = User::factory()->create();
    $follower->follow($privateUser);
    $privateUser->approveFollowRequest($follower);

    $request = Request::create(route('profile.show', ['user' => $privateUser, 'tab' => 'pets']), 'GET', [
        'tab' => 'pets',
    ]);
    $request->setUserResolver(fn () => $follower);

    /** @var View $view */
    $view = app(PublicProfileController::class)->show($request, $privateUser);
    $data = $view->getData();

    expect($data['canViewContent'])->toBeTrue();
    expect($data['pets']->pluck('name')->all())->toContain('Milo');
});

test('profile owner can view private profile pets tab', function (): void {
    $privateUser = User::factory()->create([
        'username' => 'private_owner_two',
        'is_private' => true,
    ]);

    Pet::factory()->for($privateUser)->create([
        'name' => 'Nora',
        'species' => 'dog',
    ]);

    $this->actingAs($privateUser)
        ->get(route('profile.show', ['user' => $privateUser, 'tab' => 'pets']))
        ->assertOk()
        ->assertSee('Nora');
});

test('users can follow and unfollow with counter updates', function (): void {
    $follower = User::factory()->create();
    $followed = User::factory()->create();

    $this->actingAs($follower)
        ->postJson(route('users.follow', ['user' => $followed]))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('follow_status', 'following')
        ->assertJsonPath('follower_count', 1);

    $follower->refresh();
    $followed->refresh();

    expect($follower->following_count)->toBe(1);
    expect($followed->followers_count)->toBe(1);

    $this->actingAs($follower)
        ->deleteJson(route('users.unfollow', ['user' => $followed]))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('follow_status', 'none')
        ->assertJsonPath('follower_count', 0);

    $follower->refresh();
    $followed->refresh();

    expect($follower->following_count)->toBe(0);
    expect($followed->followers_count)->toBe(0);
});

test('blocking removes follows and prevents future follows until unblocked', function (): void {
    $actor = User::factory()->create();
    $other = User::factory()->create();

    $actor->follow($other);
    $other->follow($actor);

    $this->actingAs($actor)
        ->postJson(route('users.block', ['user' => $other]))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.is_blocked', true)
        ->assertJsonPath('data.blocked_users_count', 1);

    $actor->refresh();
    $other->refresh();

    expect($actor->isFollowing($other))->toBeFalse();
    expect($other->isFollowing($actor))->toBeFalse();
    expect($actor->following_count)->toBe(0);
    expect($actor->followers_count)->toBe(0);
    expect($other->following_count)->toBe(0);
    expect($other->followers_count)->toBe(0);

    $this->actingAs($actor)
        ->postJson(route('users.follow', ['user' => $other]))
        ->assertForbidden();

    $this->actingAs($other)
        ->postJson(route('users.follow', ['user' => $actor]))
        ->assertForbidden();

    $this->actingAs($actor)
        ->deleteJson(route('users.unblock', ['user' => $other]))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.is_blocked', false)
        ->assertJsonPath('data.blocked_users_count', 0);

    $this->actingAs($actor)
        ->postJson(route('users.follow', ['user' => $other]))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('follow_status', 'following');
});

test('blocking removes pending follow requests in both directions', function (): void {
    $actor = User::factory()->create(['is_private' => true]);
    $other = User::factory()->create(['is_private' => true]);

    $this->actingAs($actor)
        ->postJson(route('users.follow', ['user' => $other]))
        ->assertOk()
        ->assertJsonPath('follow_status', 'pending');

    $this->actingAs($other)
        ->postJson(route('users.follow', ['user' => $actor]))
        ->assertOk()
        ->assertJsonPath('follow_status', 'pending');

    $actor->refresh();
    $other->refresh();

    expect($actor->follow_requests_count)->toBe(1);
    expect($other->follow_requests_count)->toBe(1);

    $this->actingAs($actor)
        ->postJson(route('users.block', ['user' => $other]))
        ->assertOk();

    $this->assertDatabaseMissing('follows', [
        'follower_id' => $actor->id,
        'following_id' => $other->id,
    ]);

    $this->assertDatabaseMissing('follows', [
        'follower_id' => $other->id,
        'following_id' => $actor->id,
    ]);

    $actor->refresh();
    $other->refresh();

    expect($actor->follow_requests_count)->toBe(0);
    expect($other->follow_requests_count)->toBe(0);
});

test('blocked users cannot request to follow private accounts', function (): void {
    $actor = User::factory()->create(['is_private' => true]);
    $blocked = User::factory()->create();

    $actor->block($blocked);

    $this->actingAs($blocked)
        ->postJson(route('users.follow', ['user' => $actor]))
        ->assertForbidden();

    $this->assertDatabaseMissing('follows', [
        'follower_id' => $blocked->id,
        'following_id' => $actor->id,
    ]);
});

test('unblocking does not restore previous follow relationships', function (): void {
    $actor = User::factory()->create();
    $other = User::factory()->create();

    $actor->follow($other);
    $other->follow($actor);

    $this->actingAs($actor)
        ->postJson(route('users.block', ['user' => $other]))
        ->assertOk();

    $this->actingAs($actor)
        ->deleteJson(route('users.unblock', ['user' => $other]))
        ->assertOk();

    $this->assertDatabaseMissing('follows', [
        'follower_id' => $actor->id,
        'following_id' => $other->id,
    ]);

    $this->assertDatabaseMissing('follows', [
        'follower_id' => $other->id,
        'following_id' => $actor->id,
    ]);
});

test('blocked users cannot view each others profile', function (): void {
    $actor = User::factory()->create();
    $other = User::factory()->create();

    $actor->block($other);

    $this->actingAs($actor)
        ->get(route('profile.show', ['user' => $other]))
        ->assertNotFound();

    $this->actingAs($actor)
        ->get(route('profile.followers', ['user' => $other]))
        ->assertNotFound();

    $this->actingAs($actor)
        ->get(route('profile.following', ['user' => $other]))
        ->assertNotFound();

    $this->actingAs($other)
        ->get(route('profile.show', ['user' => $actor]))
        ->assertNotFound();

    $this->actingAs($other)
        ->get(route('profile.followers', ['user' => $actor]))
        ->assertNotFound();

    $this->actingAs($other)
        ->get(route('profile.following', ['user' => $actor]))
        ->assertNotFound();
});

test('users can pin and unpin posts and only one post remains pinned', function (): void {
    $user = User::factory()->create();
    $first = Post::factory()->for($user)->create(['is_pinned' => false]);
    $second = Post::factory()->for($user)->create(['is_pinned' => false]);

    $this->actingAs($user)
        ->from('/feed')
        ->post(route('posts.pin', ['post' => $first]))
        ->assertRedirect('/feed');

    expect($first->refresh()->is_pinned)->toBeTrue();

    $this->actingAs($user)
        ->from('/feed')
        ->post(route('posts.pin', ['post' => $second]))
        ->assertRedirect('/feed');

    expect($first->refresh()->is_pinned)->toBeFalse();
    expect($second->refresh()->is_pinned)->toBeTrue();

    $this->actingAs($user)
        ->from('/feed')
        ->delete(route('posts.unpin', ['post' => $second]))
        ->assertRedirect('/feed');

    expect($second->refresh()->is_pinned)->toBeFalse();
});

test('users cannot pin posts they do not own', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $post = Post::factory()->for($owner)->create();

    $this->actingAs($other)
        ->post(route('posts.pin', ['post' => $post]))
        ->assertForbidden();
});

test('profile posts tab shows pinned post first', function (): void {
    $user = User::factory()->create();
    $olderPinned = Post::factory()->for($user)->create([
        'body' => 'older pinned post',
        'is_pinned' => true,
        'created_at' => now()->subDay(),
    ]);
    $newerRegular = Post::factory()->for($user)->create([
        'body' => 'newer regular post',
        'is_pinned' => false,
        'created_at' => now(),
    ]);

    $response = $this->actingAs($user)
        ->get(route('profile.show', ['user' => $user, 'tab' => 'posts']));

    $response->assertOk();
    $response->assertSeeInOrder([
        $olderPinned->body,
        $newerRegular->body,
    ]);
});

test('mutual followers appear on both followers and following pages', function (): void {
    $alice = User::factory()->create([
        'name' => 'Alice User',
        'username' => 'alice_user',
    ]);
    $bob = User::factory()->create([
        'name' => 'Bob User',
        'username' => 'bob_user',
    ]);

    $alice->follow($bob);
    $bob->follow($alice);

    expect($alice->isFollowing($bob))->toBeTrue();
    expect($alice->isFollowedBy($bob))->toBeTrue();

    $this->actingAs($alice)
        ->get(route('profile.followers', ['user' => $alice]))
        ->assertOk()
        ->assertSee('Bob User');

    $this->actingAs($alice)
        ->get(route('profile.following', ['user' => $alice]))
        ->assertOk()
        ->assertSee('Bob User');
});

test('user can delete account with correct password confirmation', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/');

    $this->assertGuest();
    expect($user->fresh()?->deleted_at)->not()->toBeNull();
});

test('delete account requires correct password confirmation', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), ['password' => 'wrong-password'])
        ->assertSessionHasErrorsIn('userDeletion', 'password')
        ->assertRedirect(route('profile.edit'));

    expect($user->fresh())->not()->toBeNull();
});

test('verified badge data is available on public profile', function (): void {
    $user = User::factory()->create([
        'username' => 'verified_user',
    ]);

    $badge = Badge::query()->create([
        'name' => 'Verified',
        'slug' => 'verified',
        'condition_type' => 'manual',
    ]);

    $user->badges()->attach($badge->getKey(), ['awarded_at' => now()]);

    $response = $this->actingAs(User::factory()->create())
        ->get(route('profile.show', ['user' => $user]));

    $response->assertOk();

    /** @var User $profileUser */
    $profileUser = $response->viewData('profileUser');

    expect($profileUser->badges()->where('slug', 'verified')->exists())->toBeTrue();
});

test('authenticated profile requests refresh online indicator timestamp', function (): void {
    $user = User::factory()->create([
        'last_seen_at' => null,
    ]);

    $now = Carbon::parse('2026-02-21 12:34:56');
    Carbon::setTestNow($now);

    try {
        $this->actingAs($user)
            ->get(route('settings.profile'))
            ->assertOk();
    } finally {
        Carbon::setTestNow();
    }

    $user->refresh();

    expect($user->last_seen_at?->toDateTimeString())->toBe('2026-02-21 12:34:56');

    Carbon::setTestNow($now);

    try {
        expect(User::query()->activeRecently()->pluck('id')->all())->toContain($user->getKey());
    } finally {
        Carbon::setTestNow();
    }
});

test('profile activity summary uses six monthly buckets', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-03-15 10:00:00'));

    try {
        $profileUser = User::factory()->create([
            'username' => 'activity_user',
            'is_private' => false,
        ]);

        Post::factory()->for($profileUser)->create([
            'created_at' => now()->subMonth()->startOfMonth()->addDay(),
        ]);
        Post::factory()->for($profileUser)->create([
            'created_at' => now()->subMonth()->startOfMonth()->addDays(2),
        ]);
        Post::factory()->for($profileUser)->create([
            'created_at' => now()->subMonths(3)->startOfMonth()->addDay(),
        ]);
        Post::factory()->for($profileUser)->create([
            'created_at' => now()->subMonths(8)->startOfMonth()->addDay(),
        ]);

        $response = $this->actingAs(User::factory()->create())
            ->get(route('profile.show', ['user' => $profileUser]));

        $response->assertOk();

        /** @var list<array{month: string, count: int}> $activityData */
        $activityData = $response->viewData('activityData');

        expect($activityData)->toHaveCount(6);

        $countsByMonth = collect($activityData)->mapWithKeys(
            fn (array $item): array => [$item['month'] => $item['count']]
        );

        expect($countsByMonth->get('Feb'))->toBe(2);
        expect($countsByMonth->get('Dec'))->toBe(1);
        expect($countsByMonth->sum())->toBe(3);
    } finally {
        Carbon::setTestNow();
    }
});
