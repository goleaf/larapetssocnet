<?php

namespace Tests\Feature;

use App\Models\Content\Post;
use App\Models\Groups\Group;
use App\Models\Groups\GroupInvitation;
use App\Models\Groups\GroupMember;
use App\Models\Identity\User;
use App\Notifications\GroupDigestReady;
use App\Notifications\GroupInvitationReceived;
use App\Notifications\GroupModerationAlert;
use App\Services\GroupDigestService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class GroupPlatformLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_groups_can_be_created_with_each_privacy_level(): void
    {
        $owner = User::factory()->create();

        foreach (['public', 'private', 'secret'] as $privacy) {
            $this->actingAs($owner)
                ->post(route('groups.store'), [
                    'name' => "Lifecycle {$privacy} group",
                    'description' => "A {$privacy} group for lifecycle coverage.",
                    'privacy' => $privacy,
                ])
                ->assertRedirect();

            $this->assertDatabaseHas('groups', [
                'name' => "Lifecycle {$privacy} group",
                'privacy' => $privacy,
                'type' => $privacy,
                'owner_id' => $owner->getKey(),
                'owner_user_id' => $owner->getKey(),
            ]);
        }
    }

    public function test_public_join_and_private_join_request_flows_are_role_aware(): void
    {
        $owner = User::factory()->create();
        $publicMember = User::factory()->create();
        $privateRequester = User::factory()->create();
        $publicGroup = $this->ownedGroup($owner, ['privacy' => 'public', 'type' => 'public']);
        $privateGroup = $this->ownedGroup($owner, ['privacy' => 'private', 'type' => 'private']);

        $this->actingAs($publicMember)
            ->post(route('groups.join', $publicGroup->slug))
            ->assertRedirect();

        $this->assertDatabaseHas('group_members', [
            'group_id' => $publicGroup->getKey(),
            'user_id' => $publicMember->getKey(),
            'role' => 'member',
            'status' => 'active',
        ]);

        $this->actingAs($privateRequester)
            ->post(route('groups.join', $privateGroup->slug))
            ->assertRedirect();

        $this->assertDatabaseHas('group_members', [
            'group_id' => $privateGroup->getKey(),
            'user_id' => $privateRequester->getKey(),
            'role' => 'member',
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('group_join_requests', [
            'group_id' => $privateGroup->getKey(),
            'user_id' => $privateRequester->getKey(),
            'status' => 'pending',
        ]);
    }

    public function test_invited_users_can_accept_or_decline_secret_group_invitations(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        $decliningInvitee = User::factory()->create();
        $group = $this->ownedGroup($owner, ['privacy' => 'secret', 'type' => 'secret']);

        $this->actingAs($owner)
            ->post(route('groups.invitations.store', $group->slug), [
                'user_id' => $invitee->getKey(),
                'message' => 'Join the planning group.',
            ])
            ->assertRedirect();

        $invitation = GroupInvitation::query()
            ->where('group_id', $group->getKey())
            ->where('invited_user_id', $invitee->getKey())
            ->firstOrFail();

        $this->assertSame('pending', (string) $invitation->status->value);
        $this->assertInstanceOf(ShouldQueue::class, new GroupInvitationReceived($group, $invitation, $owner));
        Notification::assertSentTo($invitee, GroupInvitationReceived::class);

        $this->actingAs($invitee)
            ->patch(route('groups.invitations.accept', [$group->slug, $invitation->getKey()]))
            ->assertRedirect(route('groups.show', $group->slug));

        $this->assertDatabaseHas('group_invitations', [
            'id' => $invitation->getKey(),
            'status' => 'accepted',
        ]);
        $this->assertDatabaseHas('group_members', [
            'group_id' => $group->getKey(),
            'user_id' => $invitee->getKey(),
            'status' => 'active',
        ]);

        $this->actingAs($invitee)
            ->get(route('groups.show', $group->slug))
            ->assertOk();

        $this->actingAs($owner)
            ->post(route('groups.invitations.store', $group->slug), [
                'user_id' => $decliningInvitee->getKey(),
            ])
            ->assertRedirect();

        $declinedInvitation = GroupInvitation::query()
            ->where('group_id', $group->getKey())
            ->where('invited_user_id', $decliningInvitee->getKey())
            ->firstOrFail();

        $this->actingAs($decliningInvitee)
            ->patch(route('groups.invitations.decline', [$group->slug, $declinedInvitation->getKey()]))
            ->assertRedirect(route('groups.index'));

        $this->assertDatabaseHas('group_invitations', [
            'id' => $declinedInvitation->getKey(),
            'status' => 'declined',
        ]);
        $this->assertDatabaseMissing('group_members', [
            'group_id' => $group->getKey(),
            'user_id' => $decliningInvitee->getKey(),
            'status' => 'active',
        ]);

        $this->actingAs($decliningInvitee)
            ->get(route('groups.show', $group->slug))
            ->assertNotFound();
    }

    public function test_secret_groups_are_absent_from_global_group_search_for_non_members(): void
    {
        $viewer = User::factory()->create();
        $secretGroup = Group::factory()->secret()->create([
            'name' => 'Lifecycle Hidden Handlers',
            'slug' => 'lifecycle-hidden-handlers',
        ]);
        $visibleGroup = Group::factory()->public()->create([
            'name' => 'Lifecycle Open Handlers',
            'slug' => 'lifecycle-open-handlers',
        ]);

        $this->actingAs($viewer)
            ->get(route('search.index', [
                'q' => 'Lifecycle',
                'type' => 'groups',
            ]))
            ->assertOk()
            ->assertSee($visibleGroup->name)
            ->assertDontSee($secretGroup->name);
    }

    public function test_moderators_can_remove_group_posts_and_notify_the_author(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $moderator = User::factory()->create();
        $author = User::factory()->create();
        $group = $this->ownedGroup($owner);
        $this->activeMember($group, $moderator, 'moderator');
        $this->activeMember($group, $author);

        $post = Post::factory()->for($author)->create([
            'group_id' => $group->getKey(),
            'body' => 'post removed by moderator',
        ]);
        $group->attachSharedPost($post, (int) $author->getKey());

        $this->assertInstanceOf(ShouldQueue::class, new GroupModerationAlert($group, $post, $moderator, 'removed'));

        $this->actingAs($moderator)
            ->delete(route('groups.posts.destroy', [$group->slug, $post->getKey()]))
            ->assertRedirect();

        $this->assertSoftDeleted('posts', [
            'id' => $post->getKey(),
        ]);
        Notification::assertSentTo($author, GroupModerationAlert::class);
    }

    public function test_owner_can_transfer_ownership_and_leave_afterward(): void
    {
        $owner = User::factory()->create();
        $moderator = User::factory()->create();
        $group = $this->ownedGroup($owner);
        $targetMembership = $this->activeMember($group, $moderator, 'moderator');

        $this->actingAs($owner)
            ->patch(route('groups.ownership.transfer', $group->slug), [
                'membership_id' => $targetMembership->getKey(),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('groups', [
            'id' => $group->getKey(),
            'owner_id' => $moderator->getKey(),
            'owner_user_id' => $moderator->getKey(),
        ]);
        $this->assertDatabaseHas('group_members', [
            'id' => $targetMembership->getKey(),
            'role' => 'owner',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('group_members', [
            'group_id' => $group->getKey(),
            'user_id' => $owner->getKey(),
            'role' => 'admin',
            'status' => 'active',
        ]);

        $this->actingAs($owner)
            ->delete(route('groups.leave', $group->slug))
            ->assertRedirect();

        $this->assertDatabaseMissing('group_members', [
            'group_id' => $group->getKey(),
            'user_id' => $owner->getKey(),
        ]);
    }

    public function test_archived_groups_remain_readable_but_do_not_accept_new_posts(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $group = $this->ownedGroup($owner);
        $this->activeMember($group, $member);
        $post = Post::factory()->for($owner)->create([
            'group_id' => $group->getKey(),
            'body' => 'history remains readable in archived groups',
            'body_html' => '<p>history remains readable in archived groups</p>',
        ]);
        $group->attachSharedPost($post, (int) $owner->getKey());

        $this->actingAs($owner)
            ->patch(route('groups.archive', $group->slug))
            ->assertRedirect();

        $this->actingAs($member)
            ->get(route('groups.show', $group->slug))
            ->assertOk()
            ->assertSee('history remains readable in archived groups');

        $this->actingAs($member)
            ->post(route('groups.posts.store', $group->slug), [
                'body' => 'blocked after archive',
            ])
            ->assertForbidden();
    }

    public function test_group_digest_notifications_are_sent_for_active_members_only(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $activeMember = User::factory()->create();
        $pendingMember = User::factory()->create();
        $group = $this->ownedGroup($owner);
        $this->activeMember($group, $activeMember);
        GroupMember::factory()->pending()->create([
            'group_id' => $group->getKey(),
            'user_id' => $pendingMember->getKey(),
        ]);

        Post::factory()->for($owner)->create([
            'group_id' => $group->getKey(),
            'created_at' => now()->subHour(),
        ]);

        $this->assertInstanceOf(ShouldQueue::class, new GroupDigestReady($group, 1, now()->subDay(), now()));

        app(GroupDigestService::class)->send($group->getKey(), now()->subDay(), now());

        Notification::assertSentTo($owner, GroupDigestReady::class);
        Notification::assertSentTo($activeMember, GroupDigestReady::class);
        Notification::assertNotSentTo($pendingMember, GroupDigestReady::class);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function ownedGroup(User $owner, array $attributes = []): Group
    {
        $group = Group::factory()->create(array_merge([
            'owner_user_id' => $owner->getKey(),
            'owner_id' => $owner->getKey(),
            'privacy' => 'public',
            'type' => 'public',
        ], $attributes));

        $this->activeMember($group, $owner, 'owner');

        return $group;
    }

    private function activeMember(Group $group, User $user, string $role = 'member'): GroupMember
    {
        return GroupMember::factory()->create([
            'group_id' => $group->getKey(),
            'user_id' => $user->getKey(),
            'role' => $role,
            'status' => 'active',
            'joined_at' => now(),
        ]);
    }
}
