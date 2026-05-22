<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertOk();
    }

    public function test_new_users_can_register(): void
    {
        $birthDate = now()->subYears(20);

        $response = $this->post('/register', [
            'name' => 'Test User',
            'username' => 'test_user',
            'email' => 'test@example.com',
            'password' => 'PetSocial2026!',
            'password_confirmation' => 'PetSocial2026!',
            'birth_day' => $birthDate->day,
            'birth_month' => $birthDate->month,
            'birth_year' => $birthDate->year,
            'terms' => '1',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('verification.notice', absolute: false));
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'email_verified_at' => null,
        ]);
        $this->assertSame($birthDate->toDateString(), auth()->user()->fresh()->birth_date->toDateString());
    }

    public function test_reserved_usernames_cannot_register(): void
    {
        $birthDate = now()->subYears(20);

        $this->post('/register', [
            'name' => 'Reserved User',
            'username' => 'explore',
            'email' => 'reserved@example.com',
            'password' => 'PetSocial2026!',
            'password_confirmation' => 'PetSocial2026!',
            'birth_day' => $birthDate->day,
            'birth_month' => $birthDate->month,
            'birth_year' => $birthDate->year,
            'terms' => '1',
        ])
            ->assertInvalid(['username' => 'reserved']);

        $this->assertGuest();
        $this->assertDatabaseMissing('users', [
            'email' => 'reserved@example.com',
        ]);
    }
}
