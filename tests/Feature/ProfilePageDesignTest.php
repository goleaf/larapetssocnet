<?php

use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Models\Social\Follow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

if (! function_exists('profileDesignFollowers')) {
    function profileDesignFollowers(User $owner, int $count): void
    {
        $now = now();
        $password = Hash::make('password');
        $prefix = 'power_follower_'.$owner->getKey().'_';

        foreach (array_chunk(range(1, $count), 250) as $chunk) {
            User::query()->insert(array_map(fn (int $index): array => [
                'name' => 'Power Follower '.$index,
                'username' => $prefix.$index,
                'email' => $prefix.$index.'@example.test',
                'email_verified_at' => $now,
                'password' => $password,
                'profile_visibility' => 'public',
                'is_private' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ], $chunk));
        }

        User::query()
            ->where('username', 'like', $prefix.'%')
            ->pluck('id')
            ->chunk(250)
            ->each(function ($ids) use ($owner, $now): void {
                Follow::query()->insert($ids->map(fn (int $id): array => [
                    'follower_id' => $id,
                    'following_id' => $owner->getKey(),
                    'status' => 'accepted',
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all());
            });

        $owner->forceFill(['followers_count' => $count])->saveQuietly();
    }
}

it('renders facebook-style profile sections and actions for public profiles', function (): void {
    $profileOwner = User::factory()->create([
        'name' => 'Ava Carter',
        'display_name' => 'Ava and Luna',
        'username' => 'ava_carter01',
        'headline' => 'Neighborhood rescue volunteer',
        'bio' => 'Ava shares slow weekend walks, foster wins, and practical notes for anxious rescue dogs.',
        'location' => 'Portland',
        'website' => 'https://ava.example',
        'privacy_display_location' => true,
        'is_private' => false,
    ]);

    $viewer = User::factory()->create();
    $friend = User::factory()->create([
        'name' => 'Friend User',
        'username' => 'friend_user',
    ]);

    $profileOwner->follow($friend);

    Pet::factory()->for($profileOwner)->create([
        'name' => 'Luna',
        'species' => 'dog',
    ]);

    Post::factory()->for($profileOwner)->create([
        'body' => 'profile-post-visible',
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);

    $this->actingAs($viewer)
        ->get(route('profile.show', ['user' => $profileOwner]))
        ->assertOk()
        ->assertSee('data-ui="profile-shell"', false)
        ->assertSee('data-ui="profile-header"', false)
        ->assertSee('aria-labelledby="profile-header-title"', false)
        ->assertSee('id="profile-header-title"', false)
        ->assertSee('data-ui="profile-hero"', false)
        ->assertSee('data-ui="profile-stats"', false)
        ->assertSee('data-ui="profile-stat-card"', false)
        ->assertSee('data-ui="profile-identity-panel"', false)
        ->assertSee('data-ui="profile-identity-chip"', false)
        ->assertSee('data-ui="profile-tabs"', false)
        ->assertSee('data-ui="tabs"', false)
        ->assertSee('Ava and Luna')
        ->assertSee('Neighborhood rescue volunteer')
        ->assertSee('Ava shares slow weekend walks')
        ->assertSee('Portland')
        ->assertSee('ava.example')
        ->assertSee('Intro')
        ->assertSee('Friends')
        ->assertSee('Posts')
        ->assertSee('About')
        ->assertSee('Pets')
        ->assertSee('Photos')
        ->assertSee('Followers')
        ->assertSee('Following')
        ->assertDontSee('Likes')
        ->assertSee('Follow')
        ->assertSee('Message')
        ->assertSee('min-h-11', false)
        ->assertSee('focus-visible:outline-paw', false)
        ->assertSee('Friend User')
        ->assertSee('profile-post-visible')
        ->assertDontSee('Who To Follow');
});

it('renders cached profile stats as modal and pets tab actions', function (): void {
    $profileOwner = User::factory()->create([
        'name' => 'Stats Owner',
        'username' => 'stats_owner',
        'followers_count' => 42,
        'following_count' => 17,
        'pets_count' => 3,
        'posts_count' => 9,
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);
    $follower = User::factory()->create([
        'name' => 'Follower Modal User',
        'username' => 'follower_modal_user',
    ]);
    $followedUser = User::factory()->create([
        'name' => 'Following Modal User',
        'username' => 'following_modal_user',
    ]);

    $follower->follow($profileOwner);
    $profileOwner->follow($followedUser);
    Pet::factory()->for($profileOwner)->create(['name' => 'Stats Pet']);

    $profileOwner->forceFill([
        'followers_count' => 42,
        'following_count' => 17,
        'pets_count' => 3,
        'posts_count' => 9,
    ])->saveQuietly();

    $response = $this->actingAs(User::factory()->create())
        ->get(route('profile.show', ['user' => $profileOwner]))
        ->assertOk()
        ->assertSee('data-ui="profile-stats"', false)
        ->assertSee('grid grid-cols-3 gap-3', false)
        ->assertSee('data-stat="followers"', false)
        ->assertSee('window.toggleModal(\'profile-followers-modal\')', false)
        ->assertSee('aria-controls="profile-followers-modal"', false)
        ->assertSee('data-stat="following"', false)
        ->assertSee('window.toggleModal(\'profile-following-modal\')', false)
        ->assertSee('aria-controls="profile-following-modal"', false)
        ->assertSee('data-stat="pets"', false)
        ->assertSee('$wire.activateTab(\'pets\')', false)
        ->assertSee('scrollIntoView({ behavior: \'smooth\', block: \'start\' })', false)
        ->assertSee('href="'.route('profile.show', ['user' => $profileOwner, 'tab' => 'pets']).'#profile-tabs"', false)
        ->assertSee('id="profile-tabs"', false)
        ->assertSee('scroll-mt-24', false)
        ->assertSee('data-ui="profile-followers-modal"', false)
        ->assertSee('Follower Modal User')
        ->assertSee('data-ui="profile-following-modal"', false)
        ->assertSee('Following Modal User')
        ->assertDontSee('data-stat="posts"', false)
        ->assertDontSee('data-stat="visibility"', false);

    $html = $response->getContent();
    preg_match('/<ul[^>]+data-ui="profile-stats"[\s\S]*?<\/ul>/', $html, $statsMatch);
    $statsHtml = $statsMatch[0] ?? '';

    expect($statsHtml)->toContain('42')
        ->and($statsHtml)->toContain('17')
        ->and($statsHtml)->toContain('3')
        ->and($statsHtml)->not->toContain('9')
        ->and($statsHtml)->not->toContain('Posts')
        ->and($statsHtml)->not->toContain('Visibility');
});

it('places profile relationship actions beside stats on desktop and below stats on mobile', function (): void {
    $profileOwner = User::factory()->create([
        'name' => 'Action Layout Owner',
        'username' => 'action_layout_owner',
        'followers_count' => 12,
        'following_count' => 4,
        'pets_count' => 2,
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    Pet::factory()->for($profileOwner)->create(['name' => 'Action Pet']);

    $response = $this->actingAs(User::factory()->create())
        ->get(route('profile.show', ['user' => $profileOwner]))
        ->assertOk()
        ->assertSee('data-ui="profile-stats-actions"', false)
        ->assertSee('mt-5 flex flex-col gap-3 lg:flex-row lg:items-stretch lg:justify-between', false)
        ->assertSee('data-ui="profile-stats"', false)
        ->assertSee('grid grid-cols-3 gap-3 lg:flex-1 lg:self-stretch', false)
        ->assertSee('data-ui="profile-actions"', false)
        ->assertSee('flex w-full flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center lg:w-auto lg:min-w-[18rem] lg:justify-end', false)
        ->assertSee('data-ui="profile-visitor-actions"', false)
        ->assertSee('data-ui="profile-follow-primary-action"', false)
        ->assertSee('Follow')
        ->assertSee('data-ui="profile-actions-menu-trigger"', false)
        ->assertSee('aria-label="Profile actions"', false)
        ->assertSee('Send Message')
        ->assertSee('Suggest to Friends')
        ->assertSee('Block')
        ->assertSee('Report')
        ->assertSee('Copy Profile URL');

    $html = $response->getContent();

    expect(strpos($html, 'data-ui="profile-stats-actions"'))->toBeLessThan(strpos($html, 'data-ui="profile-stats"'))
        ->and(strpos($html, 'data-ui="profile-stats"'))->toBeLessThan(strpos($html, 'data-ui="profile-actions"'))
        ->and(strpos($html, 'data-stat="pets"'))->toBeLessThan(strpos($html, 'data-ui="profile-actions"'));
});

it('renders profile action buttons from the viewer relationship state', function (): void {
    $profileOwner = User::factory()->create([
        'name' => 'Relationship Action Owner',
        'username' => 'relationship_action_owner',
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    $this->get(route('profile.show', ['user' => $profileOwner]))
        ->assertOk()
        ->assertSee('data-ui="profile-actions"', false)
        ->assertSee('Sign In to Follow')
        ->assertDontSee('>Message</a>', false)
        ->assertDontSee('@click="toggleFollow"', false);

    $this->actingAs($profileOwner)
        ->get(route('profile.show', ['user' => $profileOwner]))
        ->assertOk()
        ->assertSee('data-ui="profile-actions"', false)
        ->assertSee('data-ui="profile-owner-actions"', false)
        ->assertSee('grid w-full grid-cols-2 gap-2 sm:w-auto sm:min-w-[18rem]', false)
        ->assertSee('Edit Profile')
        ->assertSee('Share Profile')
        ->assertSee('@click="window.toggleModal(\'profile-edit-modal\')"', false)
        ->assertSee('@click="window.toggleModal(\'profile-share-modal\')"', false)
        ->assertSee('aria-controls="profile-edit-modal"', false)
        ->assertSee('aria-controls="profile-share-modal"', false)
        ->assertDontSee('Create Post')
        ->assertDontSee('Account Settings')
        ->assertDontSee('@click="toggleFollow"', false)
        ->assertDontSee('Sign In to Follow');

    $this->actingAs(User::factory()->create())
        ->get(route('profile.show', ['user' => $profileOwner]))
        ->assertOk()
        ->assertSee('data-ui="profile-actions"', false)
        ->assertSee('data-ui="profile-visitor-actions"', false)
        ->assertSee('data-ui="profile-follow-primary-action"', false)
        ->assertSee('>Follow<', false)
        ->assertDontSee('Request to Follow')
        ->assertSee('@click="toggleFollow"', false)
        ->assertSee('Send Message')
        ->assertSee('Suggest to Friends')
        ->assertSee('Block')
        ->assertSee('Report')
        ->assertSee('Copy Profile URL')
        ->assertSee('aria-label="Profile actions"', false)
        ->assertSee('data-ui="profile-actions-menu-trigger"', false)
        ->assertDontSee('Edit Profile');
});

it('keeps the message menu action disabled when profile messaging policy denies it', function (): void {
    $profileOwner = User::factory()->create([
        'name' => 'Messaging Limited Owner',
        'username' => 'messaging_limited_owner',
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    $messageUrl = route('messages.conversation', ['peer' => $profileOwner]);

    $this->actingAs(User::factory()->create())
        ->get(route('profile.show', ['user' => $profileOwner]))
        ->assertOk()
        ->assertSee('data-ui="profile-actions-menu-message"', false)
        ->assertSee('Send Message')
        ->assertSee('disabled', false)
        ->assertDontSee('href="'.$messageUrl.'"', false);
});

it('renders followed profile actions with desktop unfollow hover and a mobile confirmation sheet', function (): void {
    $profileOwner = User::factory()->create([
        'name' => 'Followed Action Owner',
        'username' => 'followed_action_owner',
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);
    $viewer = User::factory()->create();

    $viewer->follow($profileOwner);

    expect($viewer->getFollowStatus($profileOwner))->toBe('following');

    $this->actingAs($viewer)
        ->get(route('profile.show', ['user' => $profileOwner]))
        ->assertOk()
        ->assertSee('data-ui="profile-visitor-actions"', false)
        ->assertSee('data-ui="profile-follow-primary-action"', false)
        ->assertSee('data-follow-status="following"', false)
        ->assertSee('@mouseenter="followingHovered = followStatus === \'following\'"', false)
        ->assertSee("followingHovered ? 'Unfollow' : 'Following'", false)
        ->assertSee('data-ui="profile-follow-mobile-action"', false)
        ->assertSee('@click="if (followStatus === \'following\') { showUnfollowSheet = true; return; } toggleFollow()"', false)
        ->assertSee('data-ui="profile-unfollow-bottom-sheet"', false)
        ->assertSee('Unfollow &#64;followed_action_owner?', false)
        ->assertSee('data-ui="profile-unfollow-confirm-action"', false)
        ->assertSee('Keep Following')
        ->assertSee('data-ui="profile-actions-menu-trigger"', false)
        ->assertSee('Send Message')
        ->assertDontSee('data-ui="profile-mutual-message-action"', false);
});

it('renders the secondary message action only when the follow is mutual', function (): void {
    $profileOwner = User::factory()->create([
        'name' => 'Mutual Action Owner',
        'username' => 'mutual_action_owner',
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);
    $viewer = User::factory()->create([
        'name' => 'Mutual Action Viewer',
        'username' => 'mutual_action_viewer',
    ]);

    $viewer->follow($profileOwner);
    $profileOwner->follow($viewer);

    expect($viewer->getFollowStatus($profileOwner))->toBe('following')
        ->and($profileOwner->getFollowStatus($viewer))->toBe('following');

    $messageUrl = route('messages.conversation', ['peer' => $profileOwner]);

    $this->actingAs($viewer)
        ->get(route('profile.show', ['user' => $profileOwner]))
        ->assertOk()
        ->assertSee('data-ui="profile-mutual-message-action"', false)
        ->assertSee('href="'.$messageUrl.'"', false)
        ->assertSee('data-ui="profile-actions-menu-trigger"', false);
});

it('renders owner edit and share profile modals from the profile action buttons', function (): void {
    $profileOwner = User::factory()->create([
        'name' => 'Share Modal Owner',
        'display_name' => 'Share Crew',
        'username' => 'share_modal_owner',
        'bio' => 'A shareable profile with a practical bio.',
        'headline' => 'Profile modal tester',
        'location' => 'Vilnius',
        'website' => 'https://share.example',
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    $profileUrl = $profileOwner->profile_url;

    $this->actingAs($profileOwner)
        ->get(route('profile.show', ['user' => $profileOwner]))
        ->assertOk()
        ->assertSee('data-ui="profile-edit-modal"', false)
        ->assertSee('data-ui="profile-edit-modal-form"', false)
        ->assertSee('action="'.route('settings.profile.update').'"', false)
        ->assertSee('name="_method" value="PUT"', false)
        ->assertSee('id="profile_modal_name"', false)
        ->assertSee('id="profile_modal_display_name"', false)
        ->assertSee('id="profile_modal_bio"', false)
        ->assertSee('id="profile_modal_headline"', false)
        ->assertSee('id="profile_modal_location"', false)
        ->assertSee('id="profile_modal_website"', false)
        ->assertSee('Advanced settings')
        ->assertSee('Save Profile')
        ->assertSee('data-ui="profile-share-modal"', false)
        ->assertSee('data-ui="profile-share-url"', false)
        ->assertSee('value="'.$profileUrl.'"', false)
        ->assertSee('data-ui="profile-share-copy-button"', false)
        ->assertSee('navigator.clipboard?.writeText', false)
        ->assertSee('document.execCommand(\'copy\')', false)
        ->assertSee('data-ui="profile-share-qr"', false)
        ->assertSee('data-ui="profile-share-qr-code"', false)
        ->assertSee('api.qrserver.com/v1/create-qr-code', false)
        ->assertSee('data='.rawurlencode($profileUrl), false)
        ->assertSee('alt="QR code for @share_modal_owner profile URL"', false)
        ->assertSee('data-ui="profile-share-options"', false)
        ->assertSee('Copy share text')
        ->assertSee('Share on X')
        ->assertSee('Share on Facebook')
        ->assertSee('Email profile')
        ->assertSee(rawurlencode('Meet Share Crew on PetSocial: '.$profileUrl), false);
});

it('renders the profile header as the topmost full-width section in the main profile view', function (): void {
    $profileOwner = User::factory()->create([
        'name' => 'Top Header Owner',
        'username' => 'top_header_owner',
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    $response = $this->get(route('profile.show', ['user' => $profileOwner]))
        ->assertOk()
        ->assertSee('data-ui="profile-header"', false)
        ->assertSee('w-full min-w-0 overflow-hidden', false)
        ->assertDontSee('<livewire:profile-header', false)
        ->assertDontSee('wire:id="profile-header', false);

    $html = $response->getContent();

    expect(strpos($html, 'data-ui="profile-header"'))->toBeLessThan(strpos($html, 'data-ui="profile-completeness"') ?: PHP_INT_MAX)
        ->and(strpos($html, 'data-ui="profile-header"'))->toBeLessThan(strpos($html, 'data-ui="profile-tabs"'));
});

it('renders cover photos as absolute object-cover layers inside fixed responsive banners', function (): void {
    $profileOwner = User::factory()->create([
        'name' => 'Cover Photo Owner',
        'username' => 'cover_photo_owner',
        'cover_photo_path' => 'https://example.test/cover-wide.jpg',
        'cover_photo_position' => 61.25,
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    $this->get(route('profile.show', ['user' => $profileOwner]))
        ->assertOk()
        ->assertSee('data-ui="profile-cover-banner"', false)
        ->assertSee('x-ref="coverBanner"', false)
        ->assertSee('h-[140px] w-full overflow-hidden md:h-[180px] lg:h-[280px]', false)
        ->assertSee('data-ui="profile-cover-image"', false)
        ->assertSee('https://example.test/cover-wide.jpg', false)
        ->assertSee('absolute inset-0 h-full w-full select-none object-cover', false)
        ->assertSee('object-position: center ${position}%', false)
        ->assertSee('@mousedown="startCoverDrag($event)"', false)
        ->assertSee('@touchstart="startCoverDrag($event)"', false)
        ->assertSee('@mousemove.window="moveCover($event)"', false)
        ->assertSee('@touchmove.window="moveCover($event)"', false)
        ->assertDontSee('data-ui="profile-cover-fallback"', false);
});

it('shows cover repositioning controls only to the profile owner', function (): void {
    $profileOwner = User::factory()->create([
        'name' => 'Cover Owner',
        'username' => 'cover_reposition_owner',
        'cover_photo_path' => 'https://example.test/cover-owner.jpg',
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    $this->actingAs($profileOwner)
        ->get(route('profile.show', ['user' => $profileOwner]))
        ->assertOk()
        ->assertSee('data-ui="cover-reposition-trigger"', false)
        ->assertSee('Reposition cover')
        ->assertSee('data-ui="cover-reposition-actions"', false)
        ->assertSee('Save position', false)
        ->assertSee('Cancel')
        ->assertSee('$wire.saveCoverPosition(this.position)', false)
        ->assertSee('cursor-grabbing touch-none', false)
        ->assertSee('aria-busy', false);

    $this->actingAs(User::factory()->create())
        ->get(route('profile.show', ['user' => $profileOwner]))
        ->assertOk()
        ->assertDontSee('data-ui="cover-reposition-trigger"', false)
        ->assertDontSee('data-ui="cover-reposition-actions"', false)
        ->assertDontSee('Reposition cover');
});

it('renders a username-derived cover gradient fallback when no cover photo exists', function (): void {
    $profileOwner = User::factory()->create([
        'name' => 'Gradient Fallback Owner',
        'username' => 'gradient_alpha',
        'cover_photo_path' => null,
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);
    $sameUsername = User::factory()->make(['username' => 'gradient_alpha']);
    $differentUsername = User::factory()->make(['username' => 'gradient_beta']);

    $this->get(route('profile.show', ['user' => $profileOwner]))
        ->assertOk()
        ->assertSee('data-ui="profile-cover-fallback"', false)
        ->assertSee('absolute inset-0 '.$profileOwner->profile_default_gradient, false)
        ->assertDontSee('data-ui="profile-cover-image"', false)
        ->assertDontSee('bg-[color:var(--surface-muted)]', false);

    expect($profileOwner->profile_default_gradient)
        ->toBe($sameUsername->profile_default_gradient)
        ->not->toBe($differentUsername->profile_default_gradient);
});

it('renders uploaded profile avatars as circular images overlapping the cover edge', function (): void {
    $profileOwner = User::factory()->create([
        'name' => 'Avatar Owner',
        'username' => 'avatar_photo_owner',
        'avatar_path' => 'https://example.test/avatar-square.jpg',
        'profile_photo_path' => null,
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    $this->get(route('profile.show', ['user' => $profileOwner]))
        ->assertOk()
        ->assertSee('data-ui="profile-avatar"', false)
        ->assertSee('absolute left-4 -bottom-[45px] z-10 flex h-[90px] w-[90px]', false)
        ->assertSee('border-4 border-white', false)
        ->assertSee('lg:-bottom-[60px] lg:h-[120px] lg:w-[120px]', false)
        ->assertSee('data-ui="profile-avatar-image"', false)
        ->assertSee('https://example.test/avatar-square.jpg', false)
        ->assertSee('alt="Avatar Owner profile avatar"', false)
        ->assertSee('class="h-full w-full object-cover"', false)
        ->assertDontSee('data-ui="profile-avatar-initial"', false);
});

it('renders generated profile initials with the username hash palette when no avatar exists', function (): void {
    $profileOwner = User::factory()->create([
        'name' => 'Ava Carter',
        'display_name' => 'Luna Crew',
        'username' => 'avatar_alpha',
        'avatar_path' => null,
        'profile_photo_path' => null,
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);
    $sameUsername = User::factory()->make(['username' => 'avatar_alpha']);
    $differentUsername = User::factory()->make(['username' => 'avatar_beta']);

    $this->get(route('profile.show', ['user' => $profileOwner]))
        ->assertOk()
        ->assertSee('data-ui="profile-avatar-initial"', false)
        ->assertSee('aria-label="Luna Crew generated avatar"', false)
        ->assertSee($profileOwner->profile_default_avatar_color, false)
        ->assertSee('>A</span>', false)
        ->assertDontSee('data-ui="profile-avatar-image"', false);

    expect($profileOwner->profile_initial)->toBe('A')
        ->and($profileOwner->profile_default_avatar_color)
        ->toBe($sameUsername->profile_default_avatar_color)
        ->not->toBe($differentUsername->profile_default_avatar_color);
});

it('renders the profile identity stack below the avatar with expandable bio text', function (): void {
    $profileOwner = User::factory()->create([
        'name' => 'Long Bio Owner',
        'display_name' => 'Long Bio Crew',
        'username' => 'long_bio_owner',
        'bio' => collect(range(1, 36))->map(fn (int $index): string => 'Bio detail '.$index)->implode(' '),
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    $response = $this->get(route('profile.show', ['user' => $profileOwner]))
        ->assertOk()
        ->assertSee('data-ui="profile-header-identity"', false)
        ->assertSee('data-ui="profile-display-name"', false)
        ->assertSee('Long Bio Crew')
        ->assertSee('data-ui="profile-username"', false)
        ->assertSee('class="text-sm font-medium text-fur"', false)
        ->assertSee('@long_bio_owner')
        ->assertSee('data-ui="profile-header-bio"', false)
        ->assertSee('data-ui="profile-bio-text"', false)
        ->assertSee('line-clamp-3', false)
        ->assertSee('transition-[max-height] duration-300 ease-out', false)
        ->assertSee('x-bind:class="canToggle', false)
        ->assertSee('line-clamp-none', false)
        ->assertSee('x-bind:style="bioStyle()"', false)
        ->assertSee('@resize.window.debounce.150ms="measureBio()"', false)
        ->assertSee('data-ui="profile-bio-toggle"', false)
        ->assertSee('x-bind:aria-expanded="expanded.toString()"', false)
        ->assertSee('aria-controls="profile-header-bio"', false)
        ->assertSee('@click="toggleBio()"', false)
        ->assertSee("expanded ? 'Read less' : 'Read more'", false)
        ->assertSee('Read more')
        ->assertDontSee('lg:pl-36', false);

    $html = $response->getContent();

    expect(strpos($html, 'data-ui="profile-avatar"'))->toBeLessThan(strpos($html, 'data-ui="profile-header-identity"'))
        ->and(strpos($html, 'data-ui="profile-display-name"'))->toBeLessThan(strpos($html, 'data-ui="profile-username"'))
        ->and(strpos($html, 'data-ui="profile-username"'))->toBeLessThan(strpos($html, 'data-ui="profile-header-bio"'));
});

it('renders profile metadata below the bio with responsive layout and safe website display', function (): void {
    $profileOwner = User::factory()->create([
        'name' => 'Metadata Owner',
        'display_name' => 'Metadata Crew',
        'username' => 'metadata_owner',
        'bio' => 'Profile metadata appears after this introduction.',
        'location' => 'Prague',
        'website' => 'https://prus.dev/work',
        'privacy_display_location' => true,
        'created_at' => Carbon::parse('2024-02-14 12:00:00'),
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    $response = $this->get(route('profile.show', ['user' => $profileOwner]))
        ->assertOk()
        ->assertSee('data-ui="profile-metadata"', false)
        ->assertSee('aria-label="Profile metadata"', false)
        ->assertSee('flex flex-col gap-2 text-sm text-fur sm:flex-row sm:flex-wrap sm:items-center', false)
        ->assertSee('data-ui="profile-metadata-location"', false)
        ->assertSee('Prague')
        ->assertSee('data-ui="profile-metadata-website"', false)
        ->assertSee('href="https://prus.dev/work"', false)
        ->assertSee('target="_blank"', false)
        ->assertSee('rel="noopener noreferrer"', false)
        ->assertSee('prus.dev')
        ->assertSee('data-ui="profile-metadata-joined"', false)
        ->assertSee('Joined February 2024')
        ->assertSee('focus-visible:outline-paw', false)
        ->assertDontSee('Member since', false);

    $html = $response->getContent();
    preg_match('/<li data-ui="profile-metadata-website"[\s\S]*?<\/li>/', $html, $metadataWebsiteMatch);

    expect($metadataWebsiteMatch[0] ?? '')->toMatch('/<a[^>]+href="https:\/\/prus\.dev\/work"[^>]*>\s*prus\.dev\s*<\/a>/')
        ->and($metadataWebsiteMatch[0] ?? '')->not->toContain('>https://prus.dev')
        ->and(strpos($html, 'data-ui="profile-header-bio"'))->toBeLessThan(strpos($html, 'data-ui="profile-metadata"'))
        ->and(strpos($html, 'data-ui="profile-metadata-location"'))->toBeLessThan(strpos($html, 'data-ui="profile-metadata-website"'))
        ->and(strpos($html, 'data-ui="profile-metadata-website"'))->toBeLessThan(strpos($html, 'data-ui="profile-metadata-joined"'));
});

it('omits empty optional profile metadata items without placeholder spacing', function (): void {
    $profileOwner = User::factory()->create([
        'name' => 'Sparse Metadata Owner',
        'username' => 'sparse_metadata_owner',
        'bio' => 'A simple public profile.',
        'location' => null,
        'city' => null,
        'website' => null,
        'privacy_display_location' => true,
        'created_at' => Carbon::parse('2023-11-03 09:30:00'),
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    $this->get(route('profile.show', ['user' => $profileOwner]))
        ->assertOk()
        ->assertSee('data-ui="profile-metadata"', false)
        ->assertSee('data-ui="profile-metadata-joined"', false)
        ->assertSee('Joined November 2023')
        ->assertDontSee('data-ui="profile-metadata-location"', false)
        ->assertDontSee('data-ui="profile-metadata-website"', false);
});

it('renders verified badges beside profile header names with an alpine tooltip', function (): void {
    $verifiedOwner = User::factory()->create([
        'name' => 'Verified Owner',
        'display_name' => 'Verified Crew',
        'username' => 'verified_badge_owner',
        'is_verified' => true,
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);
    $unverifiedOwner = User::factory()->create([
        'name' => 'Unverified Owner',
        'username' => 'unverified_badge_owner',
        'is_verified' => false,
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    $response = $this->get(route('profile.show', ['user' => $verifiedOwner]))
        ->assertOk()
        ->assertSee('Verified Crew')
        ->assertSee('data-ui="profile-verified-badge"', false)
        ->assertSee('data-ui="profile-verified-tooltip"', false)
        ->assertSee('aria-label="Verified PetSocial account"', false)
        ->assertSee('role="tooltip"', false)
        ->assertSee('x-data="{ open: false }"', false)
        ->assertSee('@mouseenter="open = true"', false)
        ->assertSee('@click="open = true"', false)
        ->assertSee('x-bind:aria-expanded="open.toString()"', false)
        ->assertSee('bg-[#0F9F8C]/10 text-[#0F9F8C]', false)
        ->assertSee('focus-visible:outline-[#0F9F8C]', false)
        ->assertSee('This account has been verified by PetSocial as a notable pet-related account or organization.')
        ->assertDontSee('title="Verified PetSocial account"', false)
        ->assertDontSee('bg-sky-light text-paw shadow-sm', false);

    $html = $response->getContent();

    expect(strpos($html, 'Verified Crew'))->toBeLessThan(strpos($html, 'data-ui="profile-verified-badge"'));

    $this->get(route('profile.show', ['user' => $unverifiedOwner]))
        ->assertOk()
        ->assertSee('Unverified Owner')
        ->assertDontSee('data-ui="profile-verified-badge"', false)
        ->assertDontSee('Verified PetSocial account');
});

it('renders a clearer private profile lockup for authenticated visitors', function (): void {
    $profileOwner = User::factory()->create([
        'name' => 'Private Profile',
        'username' => 'private_profile_design',
        'cover_photo_path' => 'https://example.test/private-cover.jpg',
        'avatar_path' => null,
        'profile_photo_path' => null,
        'is_private' => true,
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('profile.show', ['user' => $profileOwner]))
        ->assertOk()
        ->assertSee('data-ui="private-profile-shell"', false)
        ->assertSee('data-ui="private-profile-hero"', false)
        ->assertSee('data-ui="private-profile-cover-banner"', false)
        ->assertSee('h-[140px] w-full overflow-hidden md:h-[180px] lg:h-[280px]', false)
        ->assertSee('data-ui="private-profile-cover-fallback"', false)
        ->assertSee('data-ui="private-profile-avatar"', false)
        ->assertSee('data-ui="profile-avatar-initial"', false)
        ->assertSee('h-[90px] w-[90px]', false)
        ->assertSee('lg:h-[120px] lg:w-[120px]', false)
        ->assertSee('lg:pt-16', false)
        ->assertSee('data-ui="profile-display-name"', false)
        ->assertSee('data-ui="profile-username"', false)
        ->assertSee($profileOwner->profile_default_gradient, false)
        ->assertSee($profileOwner->profile_default_avatar_color, false)
        ->assertDontSee('data-ui="private-profile-cover-image"', false)
        ->assertDontSee('https://example.test/private-cover.jpg', false)
        ->assertSee('data-profile-section="profile-header"', false)
        ->assertSee('aria-labelledby="private-profile-header-title"', false)
        ->assertSee('This account is private')
        ->assertDontSee('bg-[color:var(--surface-muted)]', false)
        ->assertSee('min-h-11', false)
        ->assertSee('data-ui="private-profile-actions"', false)
        ->assertSee('data-ui="profile-follow-primary-action"', false)
        ->assertSee('Request to Follow')
        ->assertSee('data-ui="profile-actions-menu-trigger"', false)
        ->assertSee('Send Message')
        ->assertSee('Suggest to Friends')
        ->assertSee('Block')
        ->assertSee('Report')
        ->assertSee('Copy Profile URL');
});

it('renders a requested confirmation dropdown for pending private follow requests', function (): void {
    $profileOwner = User::factory()->create([
        'name' => 'Pending Private Profile',
        'username' => 'pending_private_profile',
        'avatar_path' => null,
        'profile_photo_path' => null,
        'is_private' => true,
        'profile_visibility' => 'followers_only',
    ]);
    $viewer = User::factory()->create();

    Follow::query()->create([
        'follower_id' => $viewer->id,
        'following_id' => $profileOwner->id,
        'status' => 'pending',
        'created_at' => now(),
    ]);

    $this->actingAs($viewer)
        ->get(route('profile.show', ['user' => $profileOwner]))
        ->assertOk()
        ->assertSee('data-ui="private-profile-actions"', false)
        ->assertSee('data-ui="profile-follow-primary-action"', false)
        ->assertSee('data-follow-status="pending"', false)
        ->assertSee('Requested')
        ->assertSee('aria-controls="profile-withdraw-request-dropdown"', false)
        ->assertSee('data-ui="profile-withdraw-request-dropdown"', false)
        ->assertSee('Withdraw follow request?')
        ->assertSee('Withdraw Request')
        ->assertSee('Keep Request')
        ->assertSee('@click="if (followStatus === \'pending\') { confirmWithdraw = ! confirmWithdraw; return; } toggleFollow()"', false)
        ->assertSee('@click="confirmWithdraw = false; cancelRequest()"', false)
        ->assertSee('data-ui="profile-actions-menu-trigger"', false)
        ->assertSee('Send Message')
        ->assertSee('Suggest to Friends')
        ->assertSee('Block')
        ->assertSee('Report')
        ->assertSee('Copy Profile URL')
        ->assertDontSee('Cancel request');
});

it('renders verified badges beside private profile header names', function (): void {
    $profileOwner = User::factory()->create([
        'name' => 'Private Verified Profile',
        'username' => 'private_verified_badge',
        'avatar_path' => null,
        'profile_photo_path' => null,
        'is_private' => true,
        'profile_visibility' => 'followers_only',
        'is_verified' => true,
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('profile.show', ['user' => $profileOwner]))
        ->assertOk()
        ->assertSee('Private Verified Profile')
        ->assertSee('data-ui="profile-verified-badge"', false)
        ->assertSee('private-profile-header-verified-tooltip', false)
        ->assertSee('This account has been verified by PetSocial as a notable pet-related account or organization.');
});

it('renders an intentional empty state for brand new public profiles', function (): void {
    $profileOwner = User::factory()->create([
        'name' => 'New Member',
        'username' => 'new_member_state',
        'display_name' => null,
        'headline' => null,
        'bio' => null,
        'location' => null,
        'city' => null,
        'website' => null,
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    $this->get(route('profile.show', ['user' => $profileOwner]))
        ->assertOk()
        ->assertSee('data-ui="profile-new-state"', false)
        ->assertSee('New member')
        ->assertSee('0 pets')
        ->assertSee('0 posts')
        ->assertSee('No posts published yet.')
        ->assertDontSee('No bio added yet');
});

it('shows guests public profile information with clear join prompts', function (): void {
    $profileOwner = User::factory()->create([
        'name' => 'Guest Visible Owner',
        'username' => 'guest_visible_owner',
        'bio' => 'Public bio for guests to understand who this member is.',
        'website' => 'https://guest-visible.example',
        'location' => 'Hidden City',
        'privacy_display_location' => false,
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    $this->get(route('profile.show', ['user' => $profileOwner]))
        ->assertOk()
        ->assertSee('data-ui="profile-guest-cta"', false)
        ->assertSee('Join PetSocial')
        ->assertSee('Log In')
        ->assertSee('Public bio for guests')
        ->assertSee('guest-visible.example')
        ->assertDontSee('Hidden City')
        ->assertDontSee('>Message</a>', false);
});

it('keeps high volume profiles readable with formatted counters', function (): void {
    $profileOwner = User::factory()->create([
        'name' => 'Power User',
        'username' => 'power_profile',
        'bio' => 'A busy profile with lots of community activity.',
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    profileDesignFollowers($profileOwner, 1001);
    Pet::factory()->count(24)->for($profileOwner)->create();
    $profileOwner->forceFill(['pets_count' => 24])->saveQuietly();
    Post::factory()->count(125)->for($profileOwner)->create([
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('profile.show', ['user' => $profileOwner]))
        ->assertOk()
        ->assertSee('1,001')
        ->assertSee('24 pets')
        ->assertSee('125 posts')
        ->assertSee('data-ui="profile-stat-card"', false)
        ->assertSee('data-ui="profile-identity-chip"', false);
});

it('shows followers-only profiles as locked to guests and open to approved followers', function (): void {
    $profileOwner = User::factory()->create([
        'name' => 'Followers Only Owner',
        'username' => 'followers_only_design',
        'bio' => 'Follower-only profile bio.',
        'is_private' => true,
        'profile_visibility' => 'followers_only',
    ]);
    $follower = User::factory()->create();

    Post::factory()->for($profileOwner)->create([
        'body' => 'approved-follower-visible-post',
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);

    $this->get(route('profile.show', ['user' => $profileOwner]))
        ->assertOk()
        ->assertSee('data-ui="private-profile-shell"', false)
        ->assertSee('followers-only')
        ->assertSee('Join PetSocial')
        ->assertDontSee('approved-follower-visible-post');

    $follower->follow($profileOwner);
    $profileOwner->approveFollowRequest($follower);

    $this->actingAs($follower)
        ->get(route('profile.show', ['user' => $profileOwner]))
        ->assertOk()
        ->assertSee('data-ui="profile-shell"', false)
        ->assertSee('Follower-only profile bio.')
        ->assertSee('approved-follower-visible-post')
        ->assertDontSee('This account is private');
});

it('keeps blocked profiles completely inaccessible', function (): void {
    $profileOwner = User::factory()->create([
        'username' => 'blocked_profile_design',
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);
    $blockedViewer = User::factory()->create();

    $profileOwner->block($blockedViewer);

    $this->actingAs($blockedViewer)
        ->get(route('profile.show', ['user' => $profileOwner]))
        ->assertNotFound();
});
