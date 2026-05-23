<?php

use App\Enums\AccountStatus;
use App\Models\Identity\ReservedUsername;
use App\Models\Identity\SocialAccount;
use App\Models\Identity\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('keeps existing identity columns and adds missing auth security columns', function (): void {
    expect(Schema::hasColumn('users', 'username'))->toBeTrue()
        ->and(Schema::hasColumn('users', 'birth_date'))->toBeTrue()
        ->and(Schema::hasColumn('users', 'last_seen_at'))->toBeTrue()
        ->and(Schema::hasColumn('users', 'pending_email'))->toBeTrue()
        ->and(Schema::hasColumn('users', 'two_factor_secret'))->toBeTrue()
        ->and(Schema::hasColumn('users', 'two_factor_recovery_codes'))->toBeTrue()
        ->and(Schema::hasColumn('users', 'profile_completeness_score'))->toBeTrue()
        ->and(Schema::hasColumn('users', 'account_status'))->toBeTrue()
        ->and(Schema::hasColumn('users', 'failed_login_attempts'))->toBeTrue()
        ->and(Schema::hasColumn('users', 'last_failed_login_at'))->toBeTrue();

    $indexes = collect(Schema::getIndexes('users'))->keyBy('name');

    expect($indexes->has('users_username_unique'))->toBeTrue()
        ->and($indexes->has('users_username_lower_unique'))->toBeTrue()
        ->and($indexes->has('users_pending_email_unique'))->toBeTrue()
        ->and($indexes->has('users_account_status_index'))->toBeTrue()
        ->and($indexes->has('users_last_seen_at_index'))->toBeTrue()
        ->and($indexes->has('users_last_failed_login_at_index'))->toBeTrue();
});

it('enforces case-insensitive username uniqueness at the database layer', function (): void {
    DB::table('users')->insert([
        'name' => 'Case User',
        'email' => 'case-user@example.com',
        'username' => 'CaseUser',
        'password' => 'hashed-password',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => DB::table('users')->insert([
        'name' => 'Case Duplicate',
        'email' => 'case-duplicate@example.com',
        'username' => 'caseuser',
        'password' => 'hashed-password',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('stores social login provider data separately with encrypted token casts', function (): void {
    $user = User::factory()->create();

    $socialAccount = SocialAccount::factory()
        ->for($user)
        ->create([
            'provider' => 'google',
            'provider_id' => 'google-oauth-user-id',
            'token' => 'plain-provider-token',
            'refresh_token' => 'plain-provider-refresh-token',
            'provider_payload' => ['avatar_original' => 'https://example.com/avatar.png'],
        ]);

    $raw = DB::table('social_accounts')->where('id', $socialAccount->id)->first();

    expect(Schema::hasTable('social_accounts'))->toBeTrue()
        ->and($socialAccount->user->is($user))->toBeTrue()
        ->and($socialAccount->token)->toBe('plain-provider-token')
        ->and($socialAccount->refresh_token)->toBe('plain-provider-refresh-token')
        ->and($socialAccount->provider_payload)->toBe(['avatar_original' => 'https://example.com/avatar.png'])
        ->and($raw->token)->not->toBe('plain-provider-token')
        ->and($raw->refresh_token)->not->toBe('plain-provider-refresh-token');
});

it('casts account status and two-factor recovery codes safely', function (): void {
    $user = User::factory()->create([
        'account_status' => AccountStatus::Suspended,
        'two_factor_secret' => 'totp-secret',
        'two_factor_recovery_codes' => ['alpha-code', 'beta-code'],
        'profile_completeness_score' => 70,
        'failed_login_attempts' => 2,
        'last_failed_login_at' => now(),
    ]);

    $raw = DB::table('users')->where('id', $user->id)->first();

    expect($user->refresh()->account_status)->toBe(AccountStatus::Suspended)
        ->and($user->two_factor_secret)->toBe('totp-secret')
        ->and($user->two_factor_recovery_codes)->toBe(['alpha-code', 'beta-code'])
        ->and($user->profile_completeness_score)->toBe(70)
        ->and($user->failed_login_attempts)->toBe(2)
        ->and($user->last_failed_login_at)->not->toBeNull()
        ->and($raw->two_factor_secret)->not->toBe('totp-secret')
        ->and($raw->two_factor_recovery_codes)->not->toBe(json_encode(['alpha-code', 'beta-code']));
});

it('keeps reserved usernames case-insensitively unique', function (): void {
    ReservedUsername::query()->create([
        'username' => 'SupportDesk',
        'reason' => 'staff',
    ]);

    expect(fn () => ReservedUsername::query()->create([
        'username' => 'supportdesk',
        'reason' => 'staff duplicate',
    ]))->toThrow(QueryException::class);
});
