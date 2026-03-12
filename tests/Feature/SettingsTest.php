<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_displays_profile_settings_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/settings/profile')
            ->assertOk()
            ->assertViewIs('settings.profile');
    }

    public function test_allows_user_to_update_profile_information(): void
    {
        $user = User::factory()->create(['name' => 'Old Name', 'username' => 'old_username']);

        $this->actingAs($user)
            ->put('/settings/profile', [
                'name' => 'New Name',
                'username' => 'old_username', // unchanged
                'email' => 'new@example.com',
                'bio' => 'New bio',
            ])
            ->assertRedirect('/settings/profile')
            ->assertSessionHas('success');

        $user->refresh();
        $this->assertSame('New Name', $user->name);
        $this->assertSame('new@example.com', $user->email);
        $this->assertSame('New bio', $user->bio);
    }

    public function test_requires_confirmation_when_changing_username(): void
    {
        $user = User::factory()->create(['username' => 'old_username']);

        // Attempt without confirmation
        $this->actingAs($user)
            ->put('/settings/profile', [
                'name' => $user->name,
                'email' => $user->email,
                'username' => 'new_username',
            ])
            ->assertSessionHasErrors(['username_confirm']);

        // Attempt with confirmation
        $this->actingAs($user)
            ->put('/settings/profile', [
                'name' => $user->name,
                'email' => $user->email,
                'username' => 'new_username',
                'username_confirm' => 'old_username', // confirm old username to be authorized
            ])
            ->assertRedirect('/settings/profile');

        $this->assertSame('new_username', $user->refresh()->username);
    }

    public function test_allows_user_to_change_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
            'password_changed_at' => null,
        ]);

        $this->actingAs($user)
            ->put('/settings/password', [
                'current_password' => 'password123',
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ])
            ->assertRedirect('/settings/password')
            ->assertSessionHas('success');

        $user->refresh();
        $this->assertTrue(Hash::check('newpassword123', $user->password));
        $this->assertNotNull($user->password_changed_at);
    }

    public function test_updates_privacy_settings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put('/settings/privacy', [
                'profile_visibility' => 'followers_only',
                'messaging_permission' => 'followers_only',
                'pets_visibility' => 'followers_only',
                'groups_visibility' => 'followers_only',
            ])
            ->assertRedirect('/settings/privacy');

        $user->refresh();
        $this->assertSame('followers_only', $user->profile_visibility);
        $this->assertSame('followers_only', $user->messaging_permission);
        $this->assertFalse((bool) $user->show_in_explore);
    }

    public function test_updates_notification_preferences(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put('/settings/notifications', [
                'notifications' => [
                    'post_likes' => 1,
                    'new_follower' => 0,
                    'direct_messages' => 0,
                ],
            ])
            ->assertRedirect('/settings/notifications');

        $user->refresh();
        $this->assertTrue($user->notificationEnabled('post_likes'));
        $this->assertFalse($user->notificationEnabled('new_follower'));
        $this->assertFalse($user->notificationEnabled('direct_messages'));
    }

    public function test_blocks_and_unblocks_users(): void
    {
        $viewer = User::factory()->create();
        $target = User::factory()->create();

        // Block
        $this->actingAs($viewer)
            ->post('/settings/blocked', ['username' => $target->username])
            ->assertRedirect();

        $this->assertTrue($viewer->hasBlocked($target));
        $this->assertDatabaseHas('blocks', [
            'blocker_id' => $viewer->id,
            'blocked_id' => $target->id,
        ]);

        // Unblock
        $this->actingAs($viewer)
            ->delete("/settings/blocked/{$target->username}")
            ->assertRedirect();

        $this->assertFalse($viewer->hasBlocked($target));
    }

    public function test_initiates_account_deletion_with_proper_password_and_confirmation(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
            'scheduled_deletion_at' => null,
        ]);

        $this->actingAs($user)
            ->delete('/settings/delete-account', [
                'password' => 'password123',
                'delete_confirmation' => 'DELETE',
                'deletion_reason' => 'Testing deletion flow',
            ])
            ->assertRedirect('/');

        $user->refresh();
        $this->assertNotNull($user->scheduled_deletion_at);
        $this->assertSame('Testing deletion flow', $user->deletion_reason);

        $this->assertGuest();
    }

    public function test_cancels_account_deletion(): void
    {
        $user = User::factory()->create([
            'scheduled_deletion_at' => now()->addDays(20),
            'password' => Hash::make('password123'),
        ]);

        $this->actingAs($user)
            ->post('/settings/cancel-deletion')
            ->assertRedirect(route('dashboard'));

        $this->assertNull($user->refresh()->scheduled_deletion_at);
    }

    public function test_exports_user_data_as_json_download(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/settings/export-data')
            ->assertDownload()
            ->assertHeader('Content-Type', 'application/json');
    }

    public function test_blocked_users_page_lists_blocked_users(): void
    {
        $viewer = User::factory()->create();
        $target = User::factory()->create();

        $viewer->block($target);

        $this->actingAs($viewer)
            ->get('/settings/blocked')
            ->assertOk()
            ->assertSee($target->username);
    }
}
