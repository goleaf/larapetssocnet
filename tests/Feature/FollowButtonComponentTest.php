<?php

use App\Models\Identity\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;

uses(RefreshDatabase::class);

it('renders requested follow withdrawal confirmation state', function (): void {
    $target = User::factory()->create([
        'followers_count' => 12,
    ]);

    $html = Blade::render('<x-follow-button :user="$user" follow-status="pending" />', [
        'user' => $target,
    ]);

    expect($html)
        ->toContain('Requested')
        ->toContain('Withdraw follow request?')
        ->toContain('Withdraw');
});

it('renders hover-to-unfollow label state', function (): void {
    $target = User::factory()->create();

    $html = Blade::render('<x-follow-button :user="$user" follow-status="following" />', [
        'user' => $target,
    ]);

    expect($html)
        ->toContain("return 'Unfollow'")
        ->toContain('mouseenter')
        ->toContain('mouseleave');
});
