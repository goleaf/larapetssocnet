<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Identity\ReservedUsername;
use App\Models\Identity\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSee('data-ui="register-form"', false);
    }

    public function test_registration_route_uses_full_page_livewire_component(): void
    {
        $route = Route::getRoutes()->getByName('register');

        $this->assertNotNull($route);
        $this->assertSame('register', $route->uri());
        $this->assertSame('pages.auth.register', $route->getAction('livewire_component'));
        $this->assertContains('guest', $route->gatherMiddleware());
    }

    public function test_authenticated_users_are_redirected_to_feed_from_registration(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/register')
            ->assertRedirect(route('feed.index', absolute: false));
    }

    public function test_new_users_can_register_through_livewire(): void
    {
        Notification::fake();

        $birthDate = now()->subYears(20);

        Livewire::test('pages.auth.register')
            ->set('name', ' Test User ')
            ->set('username', 'test-user')
            ->set('email', 'test.user@gmail.com')
            ->set('password', 'PetSocial2026!')
            ->set('password_confirmation', 'PetSocial2026!')
            ->set('birth_day', (string) $birthDate->day)
            ->set('birth_month', (string) $birthDate->month)
            ->set('birth_year', (string) $birthDate->year)
            ->set('terms', true)
            ->call('register')
            ->assertHasNoErrors()
            ->assertDispatched('registration-created');

        $user = User::query()->where('email', 'test.user@gmail.com')->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('users', [
            'email' => 'test.user@gmail.com',
            'username' => 'test-user',
            'name' => 'Test User',
            'email_verified_at' => null,
        ]);
        $this->assertSame($birthDate->toDateString(), $user->birth_date->toDateString());

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_name_generates_hyphenated_username_suggestion_until_username_is_manually_edited(): void
    {
        Livewire::test('pages.auth.register')
            ->set('name', 'Ada Lovelace')
            ->assertSet('username', 'ada-lovelace')
            ->call('markUsernameManuallyEdited')
            ->set('name', 'Grace Hopper')
            ->assertSet('username', 'ada-lovelace');
    }

    public function test_reserved_usernames_cannot_register(): void
    {
        ReservedUsername::query()->create([
            'username' => 'admin',
            'reason' => 'system',
            'created_at' => now(),
        ]);

        Livewire::test('pages.auth.register')
            ->set('username', 'admin')
            ->assertHasErrors('username');

        $this->assertDatabaseMissing('users', [
            'username' => 'admin',
        ]);
    }

    public function test_username_validation_reports_format_and_uniqueness_errors(): void
    {
        User::factory()->create(['username' => 'taken-name']);

        Livewire::test('pages.auth.register')
            ->set('username', 'ab')
            ->assertHasErrors('username')
            ->set('username', 'bad username')
            ->assertHasErrors('username')
            ->set('username', '-bad')
            ->assertHasErrors('username')
            ->set('username', 'bad--name')
            ->assertHasErrors('username')
            ->set('username', 'TAKEN-NAME')
            ->assertSet('usernameAvailability', 'taken')
            ->assertHasErrors('username');
    }

    public function test_duplicate_email_is_reported_on_blur_without_case_sensitivity(): void
    {
        User::factory()->create(['email' => 'taken@gmail.com']);

        Livewire::test('pages.auth.register')
            ->set('email', 'TAKEN@gmail.com')
            ->call('validateEmailField')
            ->assertSet('emailDuplicate', true)
            ->assertHasErrors('email')
            ->assertSee('Log in instead');
    }

    public function test_underage_birth_date_is_rejected(): void
    {
        $birthDate = now()->subYears(12);

        Livewire::test('pages.auth.register')
            ->set('name', 'Young User')
            ->set('username', 'young-user')
            ->set('email', 'young.user@gmail.com')
            ->set('password', 'PetSocial2026!')
            ->set('password_confirmation', 'PetSocial2026!')
            ->set('birth_day', (string) $birthDate->day)
            ->set('birth_month', (string) $birthDate->month)
            ->set('birth_year', (string) $birthDate->year)
            ->set('terms', true)
            ->call('register')
            ->assertHasErrors('birth_date');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'young.user@gmail.com']);
    }

    public function test_weak_common_password_is_rejected(): void
    {
        $birthDate = now()->subYears(24);

        Livewire::test('pages.auth.register')
            ->set('name', 'Weak Password')
            ->set('username', 'weak-password')
            ->set('email', 'weak.password@gmail.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->set('birth_day', (string) $birthDate->day)
            ->set('birth_month', (string) $birthDate->month)
            ->set('birth_year', (string) $birthDate->year)
            ->set('terms', true)
            ->call('register')
            ->assertHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'weak.password@gmail.com']);
    }

    public function test_honeypot_registration_redirects_without_creating_records(): void
    {
        Livewire::test('pages.auth.register')
            ->set('middleName', 'Bot Value')
            ->call('register')
            ->assertRedirect(route('verification.notice'));

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('auth_audit_logs', 0);
    }
}
