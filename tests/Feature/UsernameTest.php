<?php

use App\Exceptions\UsernameChangeCooldownException;
use App\Models\Identity\ReservedUsername;
use App\Models\Identity\User;
use App\Models\Identity\UsernameRedirect;
use App\Services\ContentService;
use App\Services\UsernameService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('profile is accessible at username url', function (): void {
    $user = User::factory()->create(['username' => 'johndoe']);

    $this->get('/@johndoe')
        ->assertOk()
        ->assertSee('@johndoe');
});

it('accessing uppercase username redirects to lowercase permanently', function (): void {
    User::factory()->create(['username' => 'johndoe']);

    $this->get('/@JohnDoe')
        ->assertRedirect('/@johndoe')
        ->assertStatus(301);
});

it('old username redirects to new username for 90 days', function (): void {
    $user = User::factory()->create(['username' => 'oldname']);

    app(UsernameService::class)->change($user, 'newname');

    $this->get('/@oldname')
        ->assertRedirect('/@newname')
        ->assertStatus(301);
});

it('expired old username redirect returns 404', function (): void {
    $user = User::factory()->create(['username' => 'newname']);
    UsernameRedirect::query()->create([
        'old_username' => 'oldname',
        'user_id' => $user->id,
        'redirects_until' => now()->subDay(),
        'created_at' => now()->subDays(91),
    ]);

    $this->get('/@oldname')->assertNotFound();
});

it('username availability endpoint handles free taken and reserved values', function (): void {
    User::factory()->create(['username' => 'takenname']);
    ReservedUsername::query()->create([
        'username' => 'admin',
        'reason' => 'system',
        'created_at' => now(),
    ]);

    $this->getJson(route('api.username.available', ['username' => 'freename']))
        ->assertOk()
        ->assertJsonPath('available', true);

    $this->getJson(route('api.username.available', ['username' => 'takenname']))
        ->assertOk()
        ->assertJsonPath('available', false);

    $this->getJson(route('api.username.available', ['username' => 'admin']))
        ->assertOk()
        ->assertJsonPath('available', false);
});

it('reserved username cannot be used during registration', function (): void {
    ReservedUsername::query()->create([
        'username' => 'admin',
        'reason' => 'system',
        'created_at' => now(),
    ]);

    $this->post(route('register'), [
        'name' => 'New User',
        'username' => 'admin',
        'email' => 'new@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors('username');
});

it('username change is blocked during cooldown', function (): void {
    $user = User::factory()->create([
        'username' => 'first_name',
        'username_changed_at' => now()->subDays(5),
    ]);

    expect(fn () => app(UsernameService::class)->change($user, 'second_name'))
        ->toThrow(UsernameChangeCooldownException::class);
});

it('username helper functions return expected output', function (): void {
    $user = User::factory()->create(['username' => 'helper_user']);

    expect(at_username($user))->toBe('@helper_user');
    expect(username_url($user))->toBe(route('profile.show', ['user' => 'helper_user']));
});

it('mentions in post body are converted to clickable links', function (): void {
    User::factory()->create(['username' => 'jane_doe']);

    $html = app(ContentService::class)->process('Hello @jane_doe');

    expect($html)->toContain('/@jane_doe');
    expect($html)->toContain('<a');
});

it('mentions of non existent usernames are left as plain text', function (): void {
    $html = app(ContentService::class)->process('Hello @ghost_user');

    expect($html)->toContain('@ghost_user');
});
