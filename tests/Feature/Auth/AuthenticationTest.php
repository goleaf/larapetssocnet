<?php

namespace Tests\Feature\Auth;

use App\Models\Groups\Group;
use App\Models\Groups\GroupMember;
use App\Models\Identity\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
    }

    public function test_login_screen_does_not_expose_seeded_user_credentials(): void
    {
        $user = User::factory()->create([
            'username' => 'pet_parent',
            'email' => 'pet-parent@example.com',
        ]);

        $response = $this->get('/login');

        $response
            ->assertOk()
            ->assertDontSee('@'.$user->username)
            ->assertDontSee($user->email)
            ->assertDontSee('Quick Login Users');
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_authenticate_using_their_username_on_the_login_screen(): void
    {
        $user = User::factory()->create([
            'username' => 'username_login',
        ]);

        $response = $this->post('/login', [
            'email' => $user->username,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_login_to_an_intended_group_page_with_existing_membership(): void
    {
        $user = User::factory()->create();
        $group = Group::factory()->public()->create([
            'name' => 'City Pet Walkers',
        ]);

        GroupMember::factory()->for($group)->for($user, 'user')->create();

        $this->get(route('groups.index'))
            ->assertRedirect(route('login'));

        $this->followingRedirects()
            ->post('/login', [
                'email' => $user->email,
                'password' => 'password',
            ])
            ->assertOk()
            ->assertSee('City Pet Walkers')
            ->assertSee('Member');

        $this->assertAuthenticatedAs($user);
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
