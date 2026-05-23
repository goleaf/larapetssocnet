<?php

use App\Http\Controllers\Profile\PublicProfileController;
use App\Models\Content\Comment;
use App\Models\Content\Post;
use App\Models\Content\PostMedia;
use App\Models\Gamification\Badge;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Models\Social\Follow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Livewire\Livewire;

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

if (! function_exists('profilePhotoPost')) {
    function profilePhotoPost(User $owner, string $visibility, string $path, array $overrides = []): Post
    {
        $post = Post::factory()->for($owner)->create(array_merge([
            'body' => 'Profile photo post '.$visibility,
            'body_html' => '<p>Profile photo post '.$visibility.'</p>',
            'type' => Post::TYPE_PHOTO,
            'visibility' => $visibility,
            'created_at' => now(),
        ], $overrides));

        PostMedia::factory()->for($post, 'post')->create([
            'file_path' => $path,
            'media_type' => 'image',
            'order' => 0,
        ]);

        return $post;
    }
}

if (! function_exists('profilePhotoKey')) {
    function profilePhotoKey(Post $post, PostMedia $media): string
    {
        return sprintf('profile-photo-%s-post-media-%s', $post->getKey(), $media->getKey());
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

test('avatar uploads over three megabytes are rejected', function (): void {
    Storage::fake('public');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), profileTestPayload($user, [
            'avatar' => UploadedFile::fake()->image('avatar.jpg', 640, 640)->size(3073),
        ]))
        ->assertSessionHasErrors(['avatar'])
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->getFirstMedia(User::MEDIA_COLLECTION_AVATAR))->toBeNull();
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
    expect($data['tab'])->toBe('pets');

    Livewire::actingAs($follower)
        ->test('profile.tabs.pets', ['profileUserId' => $privateUser->getKey()])
        ->assertSee('Milo');
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

test('profile pets tab renders responsive cards with pet metadata and follow action', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-05-23 10:00:00'));

    try {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $pet = Pet::factory()->for($owner)->create([
            'name' => 'Poppy',
            'species' => 'dog',
            'breed' => 'Collie',
            'birth_date' => now()->subYears(3)->toDateString(),
            'avatar_path' => 'https://example.test/poppy.jpg',
            'followers_count' => 0,
        ]);
        User::factory()
            ->count(4)
            ->create()
            ->each(fn (User $follower): bool => $follower->followPet($pet));

        Livewire::actingAs($viewer)
            ->test('profile.tabs.pets', ['profileUserId' => $owner->getKey()])
            ->assertSee('data-ui="profile-pet-card-grid"', false)
            ->assertSee('grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3', false)
            ->assertSee('Poppy')
            ->assertSee('Dog · Collie')
            ->assertSee('3 years')
            ->assertSee('Followers')
            ->assertSee('4')
            ->assertSee('Follow Pet')
            ->assertSee('x-data="petFollowCard', false)
            ->assertSee('x-text="formatCount(count)"', false)
            ->assertSee('x-bind:aria-disabled="followed.toString()"', false)
            ->assertSee('x-bind:aria-pressed="followed.toString()"', false)
            ->assertSee('@click="follow($wire)"', false)
            ->call('followPet', $pet->getKey())
            ->assertNoRedirect();

        $this->assertDatabaseHas('pet_followers', [
            'pet_id' => $pet->getKey(),
            'user_id' => $viewer->getKey(),
        ]);

        expect($pet->fresh()->followers_count)->toBe(5);
        expect($viewer->fresh()->following_pets_count)->toBe(1);
    } finally {
        Carbon::setTestNow();
    }
});

test('profile owner sees add pet card first and visitors do not', function (): void {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();

    Pet::factory()->for($owner)->create([
        'name' => 'Existing Profile Pet',
        'species' => 'cat',
    ]);

    Livewire::actingAs($owner)
        ->test('profile.tabs.pets', ['profileUserId' => $owner->getKey()])
        ->assertSee('data-ui="profile-add-pet-card"', false)
        ->assertSee('Add a pet')
        ->assertSeeInOrder([
            'data-ui="profile-add-pet-card"',
            'Existing Profile Pet',
        ], false);

    Livewire::actingAs($viewer)
        ->test('profile.tabs.pets', ['profileUserId' => $owner->getKey()])
        ->assertSee('Existing Profile Pet')
        ->assertDontSee('data-ui="profile-add-pet-card"', false)
        ->assertDontSee('Add a pet');
});

test('profile owner with no pets sees first pet onboarding state', function (): void {
    $owner = User::factory()->create();

    Livewire::actingAs($owner)
        ->test('profile.tabs.pets', ['profileUserId' => $owner->getKey()])
        ->assertSee('data-ui="profile-pet-owner-empty"', false)
        ->assertSee('Add your first pet profile')
        ->assertSee('Pet profiles give each pet a dedicated place')
        ->assertSee('Add your first pet')
        ->assertSee('profile-pet-create-modal', false)
        ->assertDontSee('data-ui="profile-pet-card-grid"', false)
        ->assertDontSee('data-ui="profile-add-pet-card"', false)
        ->assertDontSee('This user has not added pets to their profile.');
});

test('visitor sees simple no pets message without owner call to action', function (): void {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();

    Livewire::actingAs($viewer)
        ->test('profile.tabs.pets', ['profileUserId' => $owner->getKey()])
        ->assertSee('No pets yet')
        ->assertSee('This user has not added pets to their profile.')
        ->assertDontSee('data-ui="profile-pet-owner-empty"', false)
        ->assertDontSee('Add your first pet')
        ->assertDontSee('profile-pet-create-modal', false);

    auth()->logout();

    Livewire::test('profile.tabs.pets', ['profileUserId' => $owner->getKey()])
        ->assertSee('No pets yet')
        ->assertSee('This user has not added pets to their profile.')
        ->assertDontSee('data-ui="profile-pet-owner-empty"', false)
        ->assertDontSee('Add your first pet')
        ->assertDontSee('profile-pet-create-modal', false);
});

test('profile owner can create a pet from the pets tab modal', function (): void {
    $owner = User::factory()->create([
        'username' => 'modal_pet_owner',
        'pets_count' => 0,
    ]);

    Livewire::actingAs($owner)
        ->test('profile.tabs.pets', ['profileUserId' => $owner->getKey()])
        ->assertSee('data-ui="profile-pet-owner-empty"', false)
        ->assertSee('Add your first pet')
        ->assertSee('profile-pet-create-modal', false)
        ->set('name', 'Modal Pet')
        ->set('species', 'dog')
        ->set('breed', 'Retriever')
        ->set('sex', 'female')
        ->set('age_text', '~2 years')
        ->set('bio', 'Created directly from the profile pets tab.')
        ->set('personality_tags', 'playful, gentle')
        ->set('is_public', true)
        ->call('createPet')
        ->assertHasNoErrors()
        ->assertNoRedirect()
        ->assertDispatched('profile-pet-created')
        ->assertSee('data-ui="profile-add-pet-card"', false)
        ->assertSee('Modal Pet')
        ->assertSee('Dog · Retriever')
        ->assertSee('~2 years');

    $this->assertDatabaseHas('pets', [
        'user_id' => $owner->getKey(),
        'name' => 'Modal Pet',
        'species' => 'dog',
        'breed' => 'Retriever',
        'is_public' => 1,
    ]);

    $pet = Pet::query()->where('name', 'Modal Pet')->firstOrFail();

    expect($owner->fresh()->pets_count)->toBe(1)
        ->and($pet->personality_tags)->toBe(['playful', 'gentle']);
});

test('visitors cannot create pets from another users pets tab', function (): void {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();

    Livewire::actingAs($viewer)
        ->test('profile.tabs.pets', ['profileUserId' => $owner->getKey()])
        ->set('name', 'Unauthorized Pet')
        ->set('species', 'dog')
        ->call('createPet')
        ->assertForbidden();

    $this->assertDatabaseMissing('pets', [
        'user_id' => $viewer->getKey(),
        'name' => 'Unauthorized Pet',
    ]);
});

test('profile pets tab hides follow action for already followed and own pets', function (): void {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $followedPet = Pet::factory()->for($owner)->create([
        'name' => 'Already Followed Pet',
        'followers_count' => 0,
    ]);

    $viewer->followPet($followedPet);

    Livewire::actingAs($viewer)
        ->test('profile.tabs.pets', ['profileUserId' => $owner->getKey()])
        ->assertSee('Already Followed Pet')
        ->assertDontSee('data-ui="profile-pet-follow-action"', false);

    Livewire::actingAs($owner)
        ->test('profile.tabs.pets', ['profileUserId' => $owner->getKey()])
        ->assertSee('Already Followed Pet')
        ->assertDontSee('data-ui="profile-pet-follow-action"', false);
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

test('profile posts tab shows pinned post highlight and keeps chronological feed', function (): void {
    $user = User::factory()->create();
    $olderRegular = Post::factory()->for($user)->create([
        'body' => 'older regular post',
        'body_html' => '<p>older regular post</p>',
        'is_pinned' => false,
        'created_at' => now()->subDays(5),
    ]);
    $olderPinned = Post::factory()->for($user)->create([
        'body' => 'older pinned post',
        'body_html' => '<p>older pinned post</p>',
        'is_pinned' => true,
        'pinned_at' => now(),
        'created_at' => now()->subDays(3),
    ]);
    $newerRegular = Post::factory()->for($user)->create([
        'body' => 'newer regular post',
        'body_html' => '<p>newer regular post</p>',
        'is_pinned' => false,
        'created_at' => now()->subDay(),
    ]);

    $response = $this->actingAs($user)
        ->get(route('profile.show', ['user' => $user, 'tab' => 'posts']));

    $response->assertOk();
    $response
        ->assertSee('data-ui="profile-pinned-post-section"', false)
        ->assertSee('data-ui="post-pinned-badge"', false);

    $html = $response->getContent();
    $firstPinnedPosition = strpos($html, $olderPinned->body);
    $secondPinnedPosition = strpos($html, $olderPinned->body, ((int) $firstPinnedPosition) + 1);
    $newerRegularPosition = strpos($html, $newerRegular->body);
    $olderRegularPosition = strpos($html, $olderRegular->body);

    expect(substr_count($html, $olderPinned->body))->toBe(2);
    expect($firstPinnedPosition)->toBeInt();
    expect($secondPinnedPosition)->toBeInt();
    expect($newerRegularPosition)->toBeInt();
    expect($olderRegularPosition)->toBeInt();
    expect($firstPinnedPosition)->toBeLessThan($newerRegularPosition);
    expect($newerRegularPosition)->toBeLessThan($secondPinnedPosition);
    expect($secondPinnedPosition)->toBeLessThan($olderRegularPosition);
});

test('profile pinned post highlight honors viewer visibility', function (): void {
    $owner = User::factory()->create(['is_private' => false]);
    $viewer = User::factory()->create();
    $hiddenPinned = Post::factory()->for($owner)->create([
        'body' => 'private pinned post hidden from strangers',
        'body_html' => '<p>private pinned post hidden from strangers</p>',
        'visibility' => Post::VISIBILITY_PRIVATE,
        'is_pinned' => true,
        'pinned_at' => now(),
        'created_at' => now()->subDay(),
    ]);
    Post::factory()->for($owner)->create([
        'body' => 'public regular post for strangers',
        'body_html' => '<p>public regular post for strangers</p>',
        'visibility' => Post::VISIBILITY_PUBLIC,
        'created_at' => now(),
    ]);

    $this->actingAs($viewer)
        ->get(route('profile.show', ['user' => $owner, 'tab' => 'posts']))
        ->assertOk()
        ->assertDontSee('data-ui="profile-pinned-post-section"', false)
        ->assertDontSee($hiddenPinned->body);
});

test('profile posts tab appends cursor-paginated batches without offset drift', function (): void {
    $user = User::factory()->create();
    $posts = collect(range(0, 15))
        ->map(fn (int $index): Post => Post::factory()->for($user)->create([
            'body' => 'profile cursor post '.$index,
            'body_html' => '<p>profile cursor post '.$index.'</p>',
            'created_at' => now()->subMinutes($index),
        ]));

    $component = Livewire::actingAs($user)
        ->test('profile.tabs.posts', ['profileUserId' => $user->getKey()])
        ->assertSet('postIds', $posts->take(15)->pluck('id')->all())
        ->assertSet('hasMorePosts', true)
        ->assertSee('profile cursor post 0')
        ->assertSee('profile cursor post 14')
        ->assertDontSee('profile cursor post 15')
        ->assertSee('wire:intersect.margin.400px="loadMorePosts"', false)
        ->assertSee('data-ui="profile-posts-loading-skeleton"', false);

    Post::factory()->for($user)->create([
        'body' => 'profile cursor inserted newest',
        'body_html' => '<p>profile cursor inserted newest</p>',
        'created_at' => now()->addMinute(),
    ]);

    $component
        ->call('loadMorePosts')
        ->assertSet('postIds', $posts->pluck('id')->all())
        ->assertSet('hasMorePosts', false)
        ->assertSee('profile cursor post 15')
        ->assertDontSee('profile cursor inserted newest');
});

test('profile posts tab toggles to a media grid filtered to media posts', function (): void {
    $user = User::factory()->create();
    $mediaPost = Post::factory()->for($user)->create([
        'body' => 'media timeline post',
        'body_html' => '<p>media timeline post</p>',
        'created_at' => now(),
    ]);
    $textPost = Post::factory()->for($user)->create([
        'body' => 'text only timeline post',
        'body_html' => '<p>text only timeline post</p>',
        'created_at' => now()->subMinute(),
    ]);

    PostMedia::factory()->for($mediaPost, 'post')->create([
        'file_path' => 'posts/profile-media-grid.jpg',
        'media_type' => 'image',
    ]);

    Livewire::actingAs($user)
        ->test('profile.tabs.posts', ['profileUserId' => $user->getKey()])
        ->assertSee('text only timeline post')
        ->assertSee('data-ui="profile-posts-media-toggle"', false)
        ->call('toggleMediaOnly')
        ->assertSet('mediaOnly', true)
        ->assertSet('postIds', [$mediaPost->getKey()])
        ->assertSee('data-ui="profile-posts-media-grid"', false)
        ->assertSee('data-ui="profile-media-grid-item"', false)
        ->assertDontSee($textPost->body);
});

test('profile media grid opens a full post modal with comments', function (): void {
    $user = User::factory()->create();
    $commenter = User::factory()->create();
    $post = Post::factory()->for($user)->create([
        'body' => 'modal media post body',
        'body_html' => '<p>modal media post body</p>',
        'created_at' => now(),
    ]);
    $textOnlyPost = Post::factory()->for($user)->create([
        'body' => 'modal text only post',
        'body_html' => '<p>modal text only post</p>',
        'created_at' => now()->subMinute(),
    ]);

    PostMedia::factory()->for($post, 'post')->create([
        'file_path' => 'posts/profile-modal-grid.jpg',
        'media_type' => 'image',
    ]);
    Comment::factory()->for($post)->for($commenter, 'user')->create([
        'body' => 'modal visible comment',
        'body_html' => 'modal visible comment',
    ]);

    Livewire::actingAs($user)
        ->test('profile.tabs.posts', ['profileUserId' => $user->getKey()])
        ->call('toggleMediaOnly')
        ->call('openMediaPost', $textOnlyPost->getKey())
        ->assertSet('selectedPostId', null)
        ->call('openMediaPost', $post->getKey())
        ->assertSet('selectedPostId', $post->getKey())
        ->assertSee('data-ui="profile-media-post-modal"', false)
        ->assertSee('modal media post body')
        ->assertSee('modal visible comment')
        ->call('closePostModal')
        ->assertSet('selectedPostId', null);
});

test('profile photos tab appends cursor-paginated batches by photo id', function (): void {
    $user = User::factory()->create();
    $mediaItems = collect(range(0, 30))
        ->map(function (int $index) use ($user): PostMedia {
            $post = Post::factory()->for($user)->create([
                'body' => 'profile photo cursor post '.$index,
                'body_html' => '<p>profile photo cursor post '.$index.'</p>',
                'type' => Post::TYPE_PHOTO,
                'created_at' => now()->subMinutes($index),
            ]);

            return PostMedia::factory()->for($post, 'post')->create([
                'file_path' => 'posts/profile-photo-cursor-'.$index.'.jpg',
                'media_type' => 'image',
                'order' => 0,
            ]);
        });

    $expectedFirstPageIds = $mediaItems
        ->sortByDesc(fn (PostMedia $media): int => (int) $media->getKey())
        ->take(30)
        ->pluck('id')
        ->values()
        ->all();
    $expectedAllIds = $mediaItems
        ->sortByDesc(fn (PostMedia $media): int => (int) $media->getKey())
        ->pluck('id')
        ->values()
        ->all();

    $component = Livewire::actingAs($user)
        ->test('profile.tabs.photos', ['profileUserId' => $user->getKey()])
        ->assertSet('photoMediaIds', $expectedFirstPageIds)
        ->assertSet('hasMorePhotos', true)
        ->assertSee('profile-photo-cursor-30.jpg')
        ->assertSee('profile-photo-cursor-1.jpg')
        ->assertDontSee('profile-photo-cursor-0.jpg')
        ->assertSee('wire:intersect.margin.600px="loadMorePhotos"', false)
        ->assertSee('data-ui="profile-photos-loading-skeleton"', false);

    $newPost = Post::factory()->for($user)->create([
        'body' => 'profile photo cursor inserted newest',
        'body_html' => '<p>profile photo cursor inserted newest</p>',
        'type' => Post::TYPE_PHOTO,
        'created_at' => now()->addMinute(),
    ]);
    PostMedia::factory()->for($newPost, 'post')->create([
        'file_path' => 'posts/profile-photo-cursor-newest.jpg',
        'media_type' => 'image',
        'order' => 0,
    ]);

    $component
        ->call('loadMorePhotos')
        ->assertSet('photoMediaIds', $expectedAllIds)
        ->assertSet('hasMorePhotos', false)
        ->assertSee('profile-photo-cursor-0.jpg')
        ->assertDontSee('profile-photo-cursor-newest.jpg');
});

test('profile photos tab opens a navigable lightbox with post context', function (): void {
    $owner = User::factory()->create([
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);
    $commenter = User::factory()->create([
        'name' => 'Comment Author',
    ]);
    $pet = Pet::factory()->for($owner, 'owner')->create([
        'name' => 'Milo Lightbox',
        'slug' => 'milo-lightbox',
        'is_public' => true,
    ]);

    $secondPost = profilePhotoPost($owner, Post::VISIBILITY_PUBLIC, 'posts/profile-lightbox-second.jpg', [
        'body' => 'Second lightbox post body',
        'body_html' => '<p>Second lightbox post body</p>',
        'created_at' => now()->subMinute(),
    ]);

    $firstPost = profilePhotoPost($owner, Post::VISIBILITY_PUBLIC, 'posts/profile-lightbox-first.jpg', [
        'body' => 'First lightbox post body',
        'body_html' => '<p>First lightbox post body</p>',
        'location' => 'Porto, Portugal',
        'pet_id' => $pet->getKey(),
        'tagged_pets' => [$pet->getKey()],
        'likes_count' => 5,
        'reactions_count' => 5,
        'created_at' => now(),
    ]);
    Comment::factory()->for($firstPost)->for($commenter, 'user')->create([
        'body' => 'lightbox visible comment',
        'body_html' => 'lightbox visible comment',
    ]);

    $firstMedia = $firstPost->postMedia()->firstOrFail();
    $secondMedia = $secondPost->postMedia()->firstOrFail();
    $firstPhotoKey = profilePhotoKey($firstPost, $firstMedia);
    $secondPhotoKey = profilePhotoKey($secondPost, $secondMedia);

    Livewire::actingAs($owner)
        ->test('profile.tabs.photos', ['profileUserId' => $owner->getKey()])
        ->assertSee('wire:click="openPhotoLightbox', false)
        ->assertDontSee('data-ui="profile-photo-lightbox-modal"', false)
        ->call('openPhotoLightbox', $firstPhotoKey)
        ->assertSet('selectedPhotoKey', $firstPhotoKey)
        ->assertSee('data-ui="profile-photo-lightbox-modal"', false)
        ->assertSee('role="dialog"', false)
        ->assertSee('aria-modal="true"', false)
        ->assertSee('x-data="profilePhotoLightbox()"', false)
        ->assertSee('@keydown.window="handleKeydown($event, $wire)"', false)
        ->assertSee('@touchstart.passive="startSwipe($event)"', false)
        ->assertSee('@touchend.passive="finishSwipe($event, $wire)"', false)
        ->assertSee('data-ui="profile-photo-lightbox-media"', false)
        ->assertSee('data-ui="profile-photo-lightbox-context"', false)
        ->assertSee('profile-lightbox-first.jpg')
        ->assertSee('First lightbox post body')
        ->assertSee('Milo Lightbox')
        ->assertSee('Porto, Portugal')
        ->assertSee('5 reactions')
        ->assertSee('1 comment')
        ->assertSee('lightbox visible comment')
        ->assertSee('data-ui="profile-photo-lightbox-next"', false)
        ->assertDontSee('data-ui="profile-photo-lightbox-previous"', false)
        ->call('showNextPhoto')
        ->assertSet('selectedPhotoKey', $secondPhotoKey)
        ->assertSee('Second lightbox post body')
        ->assertSee('data-ui="profile-photo-lightbox-previous"', false)
        ->assertDontSee('data-ui="profile-photo-lightbox-next"', false)
        ->call('showPreviousPhoto')
        ->assertSet('selectedPhotoKey', $firstPhotoKey)
        ->call('openPhotoLightbox', 'missing-photo-key')
        ->assertSet('selectedPhotoKey', null)
        ->call('openPhotoLightbox', $firstPhotoKey)
        ->call('closePhotoLightbox')
        ->assertSet('selectedPhotoKey', null)
        ->assertDontSee('data-ui="profile-photo-lightbox-modal"', false);
});

test('profile photos tab renders only photos from posts visible to the viewer', function (): void {
    $owner = User::factory()->create([
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);
    $nonFollower = User::factory()->create();
    $follower = User::factory()->create();
    $mutual = User::factory()->create();

    Follow::query()->create([
        'follower_id' => $follower->getKey(),
        'following_id' => $owner->getKey(),
        'status' => 'accepted',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    Follow::query()->create([
        'follower_id' => $mutual->getKey(),
        'following_id' => $owner->getKey(),
        'status' => 'accepted',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    Follow::query()->create([
        'follower_id' => $owner->getKey(),
        'following_id' => $mutual->getKey(),
        'status' => 'accepted',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $publicPhotoPost = profilePhotoPost($owner, Post::VISIBILITY_PUBLIC, 'posts/profile-public-photo.jpg', [
        'likes_count' => 7,
        'reactions_count' => 7,
    ]);
    Comment::factory()->count(3)->for($publicPhotoPost, 'post')->create();
    profilePhotoPost($owner, Post::VISIBILITY_FOLLOWERS, 'posts/profile-followers-photo.jpg');
    profilePhotoPost($owner, Post::VISIBILITY_FRIENDS, 'posts/profile-friends-photo.jpg');
    profilePhotoPost($owner, Post::VISIBILITY_PRIVATE, 'posts/profile-private-photo.jpg');

    $videoPost = Post::factory()->for($owner)->create([
        'body' => 'video-only post should not be in photos',
        'body_html' => '<p>video-only post should not be in photos</p>',
        'type' => Post::TYPE_VIDEO,
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);
    PostMedia::factory()->for($videoPost, 'post')->create([
        'file_path' => 'posts/profile-video-only.mp4',
        'media_type' => 'video',
    ]);

    Livewire::test('profile.tabs.photos', ['profileUserId' => $owner->getKey()])
        ->assertSee('data-ui="profile-photos-grid"', false)
        ->assertSee('grid grid-cols-2 gap-2 lg:grid-cols-3', false)
        ->assertSee('class="group relative aspect-square overflow-hidden', false)
        ->assertSee('h-full w-full object-cover', false)
        ->assertSee('lg:group-hover:bg-bark/45', false)
        ->assertSee('7 reactions')
        ->assertSee('3 comments')
        ->assertSee('profile-public-photo.jpg')
        ->assertDontSee('profile-followers-photo.jpg')
        ->assertDontSee('profile-friends-photo.jpg')
        ->assertDontSee('profile-private-photo.jpg')
        ->assertDontSee('profile-video-only.mp4');

    Livewire::actingAs($nonFollower)
        ->test('profile.tabs.photos', ['profileUserId' => $owner->getKey()])
        ->assertSee('profile-public-photo.jpg')
        ->assertDontSee('profile-followers-photo.jpg')
        ->assertDontSee('profile-friends-photo.jpg')
        ->assertDontSee('profile-private-photo.jpg');

    Livewire::actingAs($follower)
        ->test('profile.tabs.photos', ['profileUserId' => $owner->getKey()])
        ->assertSee('profile-public-photo.jpg')
        ->assertSee('profile-followers-photo.jpg')
        ->assertDontSee('profile-friends-photo.jpg')
        ->assertDontSee('profile-private-photo.jpg');

    Livewire::actingAs($mutual)
        ->test('profile.tabs.photos', ['profileUserId' => $owner->getKey()])
        ->assertSee('profile-public-photo.jpg')
        ->assertSee('profile-followers-photo.jpg')
        ->assertSee('profile-friends-photo.jpg')
        ->assertDontSee('profile-private-photo.jpg');

    Livewire::actingAs($owner)
        ->test('profile.tabs.photos', ['profileUserId' => $owner->getKey()])
        ->assertSee('profile-public-photo.jpg')
        ->assertSee('profile-followers-photo.jpg')
        ->assertSee('profile-friends-photo.jpg')
        ->assertSee('profile-private-photo.jpg')
        ->assertDontSee('profile-video-only.mp4');
});

test('profile photos tab hides post photos when profile content is private', function (): void {
    $owner = User::factory()->create([
        'is_private' => true,
        'profile_visibility' => 'private',
    ]);
    $viewer = User::factory()->create();

    $post = profilePhotoPost($owner, Post::VISIBILITY_PUBLIC, 'posts/private-profile-public-photo.jpg');
    $photoKey = profilePhotoKey($post, $post->postMedia()->firstOrFail());

    Livewire::actingAs($viewer)
        ->test('profile.tabs.photos', ['profileUserId' => $owner->getKey()])
        ->assertSee('Photos are private')
        ->assertDontSee('private-profile-public-photo.jpg')
        ->call('openPhotoLightbox', $photoKey)
        ->assertSet('selectedPhotoKey', null)
        ->assertDontSee('data-ui="profile-photo-lightbox-modal"', false);
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

test('verified profile badge is driven by users table flag', function (): void {
    $user = User::factory()->create([
        'username' => 'verified_user',
        'is_verified' => true,
    ]);

    $response = $this->actingAs(User::factory()->create())
        ->get(route('profile.show', ['user' => $user]));

    $response
        ->assertOk()
        ->assertSee('Verified PetSocial account');

    expect($user->fresh()?->profile_verified)->toBeTrue();
});

test('legacy badges and flags do not render the verified profile badge', function (): void {
    $user = User::factory()->create([
        'username' => 'legacy_verified_user',
        'flags' => 'verified',
        'is_verified' => false,
    ]);

    $badge = Badge::query()->create([
        'name' => 'Verified',
        'slug' => 'verified',
        'condition_type' => 'manual',
    ]);

    $user->badges()->attach($badge->getKey(), ['awarded_at' => now()]);

    $response = $this->actingAs(User::factory()->create())
        ->get(route('profile.show', ['user' => $user]));

    $response
        ->assertOk()
        ->assertDontSee('Verified PetSocial account');

    $user->refresh();

    expect($user->profile_verified)->toBeFalse()
        ->and($user->badges()->where('slug', 'verified')->exists())->toBeTrue();
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
        $activityData = Post::monthlyActivitySummaryForUser($profileUser);

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
