<?php

use App\Models\Identity\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('requires age confirmation terms and a non-common password at registration', function (): void {
    $underageDate = now()->subYears(10);

    $this->post('/register', [
        'name' => 'Young User',
        'email' => 'young@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'birth_day' => $underageDate->day,
        'birth_month' => $underageDate->month,
        'birth_year' => $underageDate->year,
    ])
        ->assertSessionHasErrors(['password', 'birth_date', 'terms']);

    expect(User::query()->where('email', 'young@example.com')->exists())->toBeFalse();
});

it('silently rejects honeypot registrations without creating an account', function (): void {
    $birthDate = now()->subYears(24);

    $this->post('/register', [
        'name' => 'Bot User',
        'email' => 'bot@example.com',
        'password' => 'PetSocial2026!',
        'password_confirmation' => 'PetSocial2026!',
        'birth_day' => $birthDate->day,
        'birth_month' => $birthDate->month,
        'birth_year' => $birthDate->year,
        'terms' => '1',
        'company_name' => 'Acme Bot',
    ])
        ->assertRedirect(route('login', absolute: false))
        ->assertSessionHas('status');

    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['email' => 'bot@example.com']);
    $this->assertDatabaseHas('auth_audit_logs', ['event_type' => 'registration_honeypot']);
});

it('records successful and failed login attempts without revealing which credential failed', function (): void {
    $user = User::factory()->create([
        'username' => 'audit_user',
        'email' => 'audit@example.com',
    ]);

    $this->post('/login', [
        'email' => 'audit_user',
        'password' => 'wrong-password',
    ])
        ->assertSessionHasErrors(['email' => trans('auth.failed')]);

    $this->assertGuest();
    $this->assertDatabaseHas('auth_audit_logs', [
        'user_id' => $user->id,
        'event_type' => 'login_failure',
    ]);

    $this->post('/login', [
        'email' => 'audit_user',
        'password' => 'password',
    ])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
    $this->assertDatabaseHas('auth_audit_logs', [
        'user_id' => $user->id,
        'event_type' => 'login_success',
    ]);
});

it('redirects unverified users away from application pages', function (): void {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(route('feed.index'))
        ->assertRedirect(route('verification.notice'));
});
