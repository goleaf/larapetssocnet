<?php

use App\Mail\Auth\VerifyEmailAddressMail;
use App\Models\Identity\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Symfony\Component\Mailer\Exception\TransportException;

uses(RefreshDatabase::class);

it('renders the email verification pending page as a full-page Livewire component', function (): void {
    $user = User::factory()->unverified()->create([
        'email' => 'joanna@example.com',
    ]);

    $this->actingAs($user)
        ->get(route('verification.notice'))
        ->assertOk()
        ->assertSee('data-ui="email-verification-panel"', false)
        ->assertSee('Check your email')
        ->assertSee('jo***@example.com')
        ->assertSee('Resend verification email')
        ->assertSee('Wrong email address? Log out');
});

it('queues a branded verification mailable when the pending page resends the email', function (): void {
    Mail::fake();
    $user = User::factory()->unverified()->create();

    RateLimiter::clear('verification-email-resend:user:'.$user->getKey());

    Livewire::actingAs($user)
        ->test('pages.auth.verify-email')
        ->call('resendVerificationEmail')
        ->assertSet('statusMessage', 'Verification email sent — check your inbox and spam folder.')
        ->assertDispatched('verification-resend-sent')
        ->assertDispatched('toast-message', message: 'Verification email sent — check your inbox and spam folder.', type: 'success');

    Mail::assertQueued(VerifyEmailAddressMail::class, function (VerifyEmailAddressMail $mail) use ($user): bool {
        return $mail->hasTo($user->email)
            && $mail->user->is($user)
            && str_contains($mail->verificationUrl, 'verify-email/'.$user->getKey());
    });

    $this->assertDatabaseHas('auth_audit_logs', [
        'user_id' => $user->getKey(),
        'event_type' => 'verification_email_resent',
    ]);
});

it('shows a resend failure message when mail delivery fails', function (): void {
    Mail::shouldReceive('to')
        ->once()
        ->andThrow(new TransportException('SMTP auth failed'));

    $user = User::factory()->unverified()->create();

    RateLimiter::clear('verification-email-resend:user:'.$user->getKey());

    Livewire::actingAs($user)
        ->test('pages.auth.verify-email')
        ->call('resendVerificationEmail')
        ->assertSet('errorMessage', 'We could not send that email right now. Please try again later.');

    $this->assertDatabaseMissing('auth_audit_logs', [
        'user_id' => $user->getKey(),
        'event_type' => 'verification_email_resent',
    ]);
});

it('rate limits verification email resends per user', function (): void {
    Mail::fake();
    $user = User::factory()->unverified()->create();
    $key = 'verification-email-resend:user:'.$user->getKey();

    RateLimiter::clear($key);
    RateLimiter::hit($key, 3600);
    RateLimiter::hit($key, 3600);
    RateLimiter::hit($key, 3600);

    Livewire::actingAs($user)
        ->test('pages.auth.verify-email')
        ->call('resendVerificationEmail')
        ->assertSet('errorMessage', 'You have requested too many verification emails. Please wait before trying again.');

    Mail::assertNothingQueued();
});

it('renders the branded verification mailable with a button and fallback URL', function (): void {
    $user = User::factory()->unverified()->create(['name' => 'Mira']);
    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->getKey(),
        'hash' => sha1($user->email),
    ]);

    $mail = new VerifyEmailAddressMail($user, $url);

    $mail->assertHasSubject('Welcome to PetSocial - please verify your email');
    $mail->assertSeeInHtml('PetSocial');
    $mail->assertSeeInHtml('Verify my email');
    $mail->assertSeeInHtml($url);
    $mail->assertSeeInText($url);
});

it('verifies a signed email link for the authenticated user and records an audit event', function (): void {
    $user = User::factory()->unverified()->create([
        'onboarding_step' => '1',
        'onboarding_completed' => false,
        'onboarding_completed_at' => null,
    ]);

    Event::fake();

    $response = $this->actingAs($user)->get(verificationUrlFor($user));

    Event::assertDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    $response->assertRedirect(route('onboarding.show', absolute: false).'?verified=1');
    $this->assertDatabaseHas('auth_audit_logs', [
        'user_id' => $user->getKey(),
        'event_type' => 'email_verified',
    ]);
});

it('verifies a signed email link opened from another browser session', function (): void {
    $user = User::factory()->unverified()->create([
        'onboarding_step' => '1',
        'onboarding_completed' => false,
        'onboarding_completed_at' => null,
    ]);

    $response = $this->get(verificationUrlFor($user));

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('onboarding.show', absolute: false).'?verified=1');
});

it('redirects expired verification links back to the pending page with a flash message', function (): void {
    $user = User::factory()->unverified()->create();

    $expiredUrl = URL::temporarySignedRoute('verification.verify', now()->subMinute(), [
        'id' => $user->getKey(),
        'hash' => sha1($user->email),
    ]);

    $this->actingAs($user)
        ->get($expiredUrl)
        ->assertRedirect(route('verification.notice'))
        ->assertSessionHas('status', 'verification-link-expired');

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('shows the pending page for an expired verification link opened from another browser session', function (): void {
    $user = User::factory()->unverified()->create();

    $expiredUrl = URL::temporarySignedRoute('verification.verify', now()->subMinute(), [
        'id' => $user->getKey(),
        'hash' => sha1($user->email),
    ]);

    $this->get($expiredUrl)
        ->assertRedirect(route('verification.notice'))
        ->assertSessionHas('status', 'verification-link-expired');

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('rejects tampered verification signatures with a forbidden response', function (): void {
    $user = User::factory()->unverified()->create();
    $tamperedUrl = preg_replace('/signature=[^&]+/', 'signature=tampered', verificationUrlFor($user));

    $this->actingAs($user)
        ->get((string) $tamperedUrl)
        ->assertForbidden();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('rejects signed verification links whose email hash does not match the user', function (): void {
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->getKey(),
        'hash' => sha1('wrong-email@example.com'),
    ]);

    $this->actingAs($user)
        ->get($url)
        ->assertForbidden();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

function verificationUrlFor(User $user): string
{
    return URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->getKey(),
        'hash' => sha1($user->email),
    ]);
}
