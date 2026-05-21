<?php

use App\Models\Identity\User;
use App\Models\Identity\UsernameRedirect;
use App\Models\Pets\Pet;
use App\Models\Pets\PhotoGallery;
use App\Models\Security\AuthAuditLog;
use App\Services\UsernameService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows public profiles to guests and authenticated viewers', function (): void {
    $user = User::factory()->create([
        'username' => 'public_user',
        'profile_visibility' => 'public',
        'is_private' => false,
    ]);

    $this->get(route('profile.show', ['user' => $user]))
        ->assertOk()
        ->assertSee('@public_user')
        ->assertSee('Log In', false);

    $this->actingAs(User::factory()->create())
        ->get(route('profile.show', ['user' => $user]))
        ->assertOk()
        ->assertSee('@public_user');
});

it('owners can view their own private profiles', function (): void {
    $user = User::factory()->create([
        'username' => 'private_owner',
        'profile_visibility' => 'private',
        'is_private' => true,
    ]);

    $this->actingAs($user)
        ->get(route('profile.show', ['user' => $user]))
        ->assertOk()
        ->assertSee('@private_owner');
});

it('accepted followers can view followers-only profiles', function (): void {
    $owner = User::factory()->create([
        'username' => 'followers_only',
        'profile_visibility' => 'followers_only',
        'is_private' => true,
    ]);
    $follower = User::factory()->create();

    $follower->follow($owner);
    $owner->approveFollowRequest($follower);

    $this->actingAs($follower)
        ->get(route('profile.show', ['user' => $owner]))
        ->assertOk()
        ->assertSee('@followers_only');
});

it('pending followers cannot view followers-only profiles', function (): void {
    $owner = User::factory()->create([
        'username' => 'pending_owner',
        'profile_visibility' => 'followers_only',
        'is_private' => true,
    ]);
    $requester = User::factory()->create();

    $requester->follow($owner);

    $this->actingAs($requester)
        ->get(route('profile.show', ['user' => $owner]))
        ->assertOk()
        ->assertSee('followers-only');
});

it('authenticated strangers cannot view private profiles', function (): void {
    $owner = User::factory()->create([
        'username' => 'strict_private',
        'profile_visibility' => 'private',
        'is_private' => true,
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('profile.show', ['user' => $owner]))
        ->assertOk()
        ->assertSee('Only you can view this profile');
});

it('blocked users cannot access profiles or username redirects', function (): void {
    $owner = User::factory()->create([
        'username' => 'oldname',
        'profile_visibility' => 'public',
        'is_private' => false,
    ]);
    $viewer = User::factory()->create();

    app(UsernameService::class)->change($owner, 'newname', $owner);
    $owner->block($viewer);

    $this->actingAs($viewer)
        ->get(route('profile.show', ['user' => $owner]))
        ->assertNotFound();

    $this->actingAs($viewer)
        ->get(route('profile.show', ['user' => 'oldname']))
        ->assertNotFound();
});

it('followers list respects privacy settings', function (): void {
    $owner = User::factory()->create([
        'profile_visibility' => 'private',
        'is_private' => true,
    ]);
    $viewer = User::factory()->create();

    $this->actingAs($viewer)
        ->get(route('profile.followers', ['user' => $owner]))
        ->assertForbidden();
});

it('hides unavailable profile owners and their old username redirects', function (array $attributes): void {
    $owner = User::factory()->create([
        'username' => 'unavailable_owner',
        ...$attributes,
    ]);

    UsernameRedirect::query()->create([
        'old_username' => 'unavailable_old',
        'user_id' => $owner->id,
        'redirects_until' => now()->addDays(90),
        'created_at' => now(),
    ]);

    $viewer = User::factory()->create();

    $this->actingAs($viewer)
        ->get('/@unavailable_owner')
        ->assertNotFound();

    $this->actingAs($viewer)
        ->get('/@unavailable_old')
        ->assertNotFound();
})->with([
    'banned' => [['is_banned' => true]],
    'pending deletion' => [['scheduled_deletion_at' => now()->addDays(10)]],
    'deactivated' => [['deactivated_at' => now()->subDay()]],
    'suspended' => [['suspended_until' => now()->addDay()]],
]);

it('does not resolve reserved old username redirects', function (): void {
    $owner = User::factory()->create(['username' => 'safe_target']);

    UsernameRedirect::query()->create([
        'old_username' => 'support',
        'user_id' => $owner->id,
        'redirects_until' => now()->addDays(90),
        'created_at' => now(),
    ]);

    $this->actingAs(User::factory()->create())
        ->get('/@support')
        ->assertNotFound();
});

it('does not expose pet tabs or pet counts when the pets section is followers only', function (): void {
    $owner = User::factory()->create([
        'username' => 'hidden_pets_owner',
        'pets_visibility' => 'followers_only',
    ]);

    Pet::factory()->for($owner)->create([
        'name' => 'Secret Pet',
        'is_public' => true,
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('profile.show', ['user' => $owner]))
        ->assertOk()
        ->assertDontSee('Pets</', false)
        ->assertDontSee('Secret Pet');

    $this->actingAs(User::factory()->create())
        ->get(route('profile.show', ['user' => $owner, 'tab' => 'pets']))
        ->assertOk()
        ->assertSee('Pets are private')
        ->assertDontSee('Secret Pet');
});

it('does not expose following counts or links when following visibility is closed', function (): void {
    $owner = User::factory()->create([
        'username' => 'closed_following',
        'open_following' => false,
    ]);
    $followed = User::factory()->create(['name' => 'Hidden Followed User']);
    $owner->follow($followed);

    $this->actingAs(User::factory()->create())
        ->get(route('profile.show', ['user' => $owner]))
        ->assertOk()
        ->assertDontSee(route('profile.following', ['user' => $owner]), false)
        ->assertDontSee('Hidden Followed User');

    $this->actingAs(User::factory()->create())
        ->get(route('profile.following', ['user' => $owner]))
        ->assertForbidden();
});

it('does not search public profiles by private email address', function (): void {
    $owner = User::factory()->create([
        'name' => 'Visible Search Name',
        'username' => 'visible_search_name',
        'email' => 'private-address@example.test',
        'profile_visibility' => 'public',
        'is_private' => false,
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('search.index', ['type' => 'users', 'q' => 'private-address@example.test']))
        ->assertOk()
        ->assertDontSee('Search result: Visible Search Name', false);
});

it('protects photo galleries with profile visibility rules', function (): void {
    $owner = User::factory()->create([
        'username' => 'private_gallery_owner',
        'profile_visibility' => 'private',
        'is_private' => true,
    ]);
    $gallery = PhotoGallery::query()->create([
        'user_id' => $owner->id,
        'title' => 'Private Gallery',
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('photo-galleries.show', ['user' => $owner, 'gallery' => $gallery]))
        ->assertNotFound();
});

it('records a safe audit event when profile settings are updated', function (): void {
    $user = User::factory()->create([
        'username' => 'audit_profile',
        'bio' => 'Old bio',
    ]);

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'display_name' => 'Audit Profile',
            'username' => $user->username,
            'email' => $user->email,
            'bio' => 'Updated private profile bio.',
        ])
        ->assertRedirect(route('profile.edit'));

    $auditLog = AuthAuditLog::query()
        ->where('user_id', $user->id)
        ->where('event_type', 'profile_updated')
        ->latest()
        ->first();

    expect($auditLog)->not->toBeNull()
        ->and($auditLog->metadata['changed_fields'])->toContain('bio')
        ->and($auditLog->metadata)->not->toHaveKey('bio');
});
