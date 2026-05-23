<?php

use App\Models\Identity\User;
use App\Models\Security\AuthAuditLog;
use App\Services\Auth\DeviceSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('renders active device sessions with parsed device and local geolocation details', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    session()->setId('current-session');

    insertDeviceSession('current-session', $user->id, '203.0.113.10', safariUserAgent(), now()->timestamp);
    insertDeviceSession('other-device-session', $user->id, '198.51.100.20', mobileSafariUserAgent(), now()->subMinutes(3)->timestamp);
    insertDeviceSession('hidden-session', $otherUser->id, '192.0.2.40', chromeUserAgent(), now()->timestamp);

    $sessions = app(DeviceSessionService::class)->activeSessions($user, 'current-session');

    expect(collect($sessions)->firstWhere('id', 'current-session')['is_current'] ?? false)->toBeTrue();

    $this->actingAs($user)
        ->get(route('settings.password'))
        ->assertOk()
        ->assertSee('data-ui="device-sessions-list"', false)
        ->assertSee('Safari 17.0 on Mac 14.0')
        ->assertSee('Example City, United States')
        ->assertSee('IP 203.0.113.10')
        ->assertSee('Active 3 minutes ago')
        ->assertSee('Log out of this device?')
        ->assertDontSee('192.0.2.40');
});

it('logs out a selected non-current device session only for the authenticated user', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    session()->setId('current-session');

    insertDeviceSession('current-session', $user->id, '203.0.113.10', safariUserAgent(), now()->timestamp);
    insertDeviceSession('other-device-session', $user->id, '198.51.100.20', mobileSafariUserAgent(), now()->subMinutes(3)->timestamp);
    insertDeviceSession('hidden-session', $otherUser->id, '192.0.2.40', chromeUserAgent(), now()->timestamp);

    Livewire::actingAs($user)
        ->test('settings.device-sessions')
        ->call('destroySession', 'other-device-session')
        ->assertSet('statusMessage', 'That device has been logged out.');

    expect(DB::table('sessions')->where('id', 'other-device-session')->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('id', 'current-session')->exists())->toBeTrue()
        ->and(DB::table('sessions')->where('id', 'hidden-session')->exists())->toBeTrue();

    $this->assertDatabaseHas('auth_audit_logs', [
        'user_id' => $user->id,
        'event_type' => 'other_sessions_logged_out',
    ]);
});

it('requires the current password before logging out all other devices and clears persistent login tokens', function (): void {
    $user = User::factory()->create([
        'remember_token' => 'persistent-token',
    ]);
    $otherUser = User::factory()->create();

    $component = Livewire::actingAs($user)->test('settings.device-sessions');
    $currentSessionId = session()->getId();

    insertDeviceSession($currentSessionId, $user->id, '203.0.113.10', safariUserAgent(), now()->timestamp);
    insertDeviceSession('other-device-session', $user->id, '198.51.100.20', mobileSafariUserAgent(), now()->subMinutes(3)->timestamp);
    insertDeviceSession('hidden-session', $otherUser->id, '192.0.2.40', chromeUserAgent(), now()->timestamp);

    $component
        ->call('openLogoutOtherDevicesModal')
        ->set('password', 'wrong-password')
        ->call('logoutOtherDevices')
        ->assertHasErrors(['password'])
        ->set('password', 'password')
        ->call('logoutOtherDevices')
        ->assertSet('confirmingLogoutOtherDevices', false)
        ->assertSet('statusMessage', 'You have been logged out of all other devices.');

    expect(DB::table('sessions')->where('id', 'other-device-session')->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('id', $currentSessionId)->exists())->toBeTrue()
        ->and(DB::table('sessions')->where('id', 'hidden-session')->exists())->toBeTrue()
        ->and($user->refresh()->remember_token)->toBeNull();

    expect(AuthAuditLog::query()
        ->where('user_id', $user->id)
        ->where('event_type', 'other_sessions_logged_out')
        ->exists())->toBeTrue();
});

function insertDeviceSession(string $id, int $userId, string $ipAddress, string $userAgent, int $lastActivity): void
{
    DB::table('sessions')->insert([
        'id' => $id,
        'user_id' => $userId,
        'ip_address' => $ipAddress,
        'user_agent' => $userAgent,
        'payload' => 'payload',
        'last_activity' => $lastActivity,
    ]);
}

function safariUserAgent(): string
{
    return 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15';
}

function mobileSafariUserAgent(): string
{
    return 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Mobile/15E148 Safari/604.1';
}

function chromeUserAgent(): string
{
    return 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
}
