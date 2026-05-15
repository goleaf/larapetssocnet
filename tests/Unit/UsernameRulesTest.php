<?php

declare(strict_types=1);

use App\Models\Identity\User;
use App\Support\Usernames\UsernameNormalizer;
use App\Support\Usernames\UsernameRules;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('normalizes usernames to canonical lowercase', function (): void {
    expect(UsernameNormalizer::normalize('..InVaLiD Name__'))->toBe('invalidname');
});

it('blocks numeric-only usernames when configured', function (): void {
    expect(UsernameRules::isAvailable('12345'))->toBeFalse();
});

it('treats reserved usernames as unavailable', function (): void {
    expect(UsernameRules::isReserved('settings'))->toBeTrue();
    expect(UsernameRules::isAvailable('settings'))->toBeFalse();
});

it('treats usernames as case-insensitively unique', function (): void {
    User::factory()->create(['username' => 'CaseTest']);

    expect(UsernameRules::isAvailable('casetest'))->toBeFalse();
    expect(UsernameRules::isAvailable('CaseTest'))->toBeFalse();
});
