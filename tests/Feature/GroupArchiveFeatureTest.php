<?php

use App\Models\Content\Comment;
use App\Models\Content\Post;
use App\Models\Groups\Group;
use App\Models\Groups\GroupMember;
use App\Models\Identity\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lets the owner archive and restore a group', function (): void {
    $owner = User::factory()->create();
    $group = Group::factory()->create([
        'owner_user_id' => $owner->getKey(),
        'owner_id' => $owner->getKey(),
    ]);

    $this->actingAs($owner)
        ->patch(route('groups.archive', $group->slug))
        ->assertRedirect();

    $group->refresh();

    expect($group->isArchived())->toBeTrue();
    $this->assertDatabaseHas('groups', [
        'id' => $group->getKey(),
        'status' => 'archived',
    ]);

    $this->actingAs($owner)
        ->get(route('groups.show', $group->slug))
        ->assertOk()
        ->assertSee('This group is archived.')
        ->assertDontSee('Share in this group');

    $this->actingAs($owner)
        ->patch(route('groups.unarchive', $group->slug))
        ->assertRedirect();

    $group->refresh();

    expect($group->isArchived())->toBeFalse();
    $this->assertDatabaseHas('groups', [
        'id' => $group->getKey(),
        'status' => 'active',
        'archived_at' => null,
    ]);
});

it('keeps archived groups readable but blocks new posts and joins', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $stranger = User::factory()->create();

    $group = Group::factory()->archived()->create([
        'owner_user_id' => $owner->getKey(),
        'owner_id' => $owner->getKey(),
        'privacy' => 'public',
        'type' => 'public',
    ]);

    GroupMember::factory()->create([
        'group_id' => $group->getKey(),
        'user_id' => $member->getKey(),
        'role' => 'member',
        'status' => 'active',
        'joined_at' => now(),
    ]);

    $post = Post::factory()->for($owner)->create([
        'group_id' => $group->getKey(),
        'body' => 'archived group history remains readable',
        'body_html' => '<p>archived group history remains readable</p>',
    ]);

    $group->attachSharedPost($post, (int) $owner->getKey());

    $this->actingAs($member)
        ->get(route('groups.show', $group->slug))
        ->assertOk()
        ->assertSee('archived group history remains readable')
        ->assertSee('This group is archived.');

    $this->actingAs($member)
        ->post(route('groups.posts.store', $group->slug), [
            'body' => 'new archived activity',
        ])
        ->assertForbidden();

    $this->actingAs($member)
        ->post(route('posts.comments.store', $post), [
            'body' => 'new archived comment',
        ])
        ->assertForbidden();

    $this->actingAs($member)
        ->postJson(route('posts.react', $post), [
            'type' => 'love',
        ])
        ->assertForbidden();

    $this->actingAs($stranger)
        ->post(route('groups.join', $group->slug))
        ->assertForbidden();
});

it('blocks comment reactions on archived group posts', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();

    $group = Group::factory()->archived()->create([
        'owner_user_id' => $owner->getKey(),
        'owner_id' => $owner->getKey(),
    ]);

    GroupMember::factory()->create([
        'group_id' => $group->getKey(),
        'user_id' => $member->getKey(),
        'role' => 'member',
        'status' => 'active',
        'joined_at' => now(),
    ]);

    $post = Post::factory()->for($owner)->create([
        'group_id' => $group->getKey(),
    ]);

    $comment = Comment::factory()->for($post)->for($owner, 'user')->create();

    $this->actingAs($member)
        ->postJson(route('comments.react', $comment), [
            'type' => 'love',
        ])
        ->assertForbidden();
});

it('hides secret groups from discovery when privacy and type columns drift', function (): void {
    $viewer = User::factory()->create();

    Group::factory()->create([
        'name' => 'Needle Secret Group',
        'slug' => 'needle-secret-group',
        'privacy' => 'secret',
        'type' => 'public',
    ]);

    Group::factory()->create([
        'name' => 'Needle Public Group',
        'slug' => 'needle-public-group',
        'privacy' => 'public',
        'type' => 'public',
    ]);

    $this->actingAs($viewer)
        ->get(route('groups.index', ['q' => 'Needle']))
        ->assertOk()
        ->assertSee('Needle Public Group')
        ->assertDontSee('Needle Secret Group');
});
