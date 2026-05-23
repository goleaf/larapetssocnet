<?php

use App\Models\Identity\User;
use App\Support\Auth\PasswordPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    $this->get('/reset-password/example-token')
        ->assertOk()
        ->assertSee($expectedAttribute, false);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('settings.password', absolute: false))
        ->assertOk()
        ->assertSee($expectedAttribute, false);
});

it('does not render password rules on current password fields', function (): void {
    $this->get('/login')
        ->assertOk()
        ->assertDontSee('passwordrules=', false);
});
