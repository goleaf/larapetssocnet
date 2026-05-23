<?php

use App\Models\Identity\User;
use App\Support\Auth\PasswordPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Validation\Rules\Password;

uses(RefreshDatabase::class);

it('uses the configured Laravel password policy for html password rules', function (): void {
    $defaultRule = Password::defaults();

    expect($defaultRule)
        ->toBeInstanceOf(Password::class)
        ->and($defaultRule->toPasswordRulesString())
        ->toBe('minlength: 8; maxlength: 128;')
        ->and(PasswordPolicy::htmlRules())
        ->toBe($defaultRule->toPasswordRulesString());
});

it('renders browser password rules on new password forms', function (): void {
    $expectedAttribute = 'passwordrules="'.e(PasswordPolicy::htmlRules()).'"';

    $this->get('/register')
        ->assertOk()
        ->assertSee($expectedAttribute, false);

    $resetUser = User::factory()->create();
    $token = PasswordBroker::broker()->createToken($resetUser);

    DB::table('password_reset_tokens')
        ->where('email', $resetUser->email)
        ->update(['token_hash' => hash('sha256', $token)]);

    $this->get(route('password.reset', ['token' => $token]))
        ->assertOk()
        ->assertSee($expectedAttribute, false);

    $this->actingAs(User::factory()->create())
        ->get(route('settings.password', absolute: false))
        ->assertOk()
        ->assertSee($expectedAttribute, false);
});

it('does not render password rules on current password fields', function (): void {
    $this->get('/login')
        ->assertOk()
        ->assertDontSee('passwordrules=', false);
});
