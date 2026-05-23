<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('keeps login failures from crashing when the remote audit table still uses legacy metadata columns', function (): void {
    Schema::drop('auth_audit_logs');

    Schema::create('auth_audit_logs', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('user_id')->nullable();
        $table->string('event_type');
        $table->string('ip_address', 45);
        $table->text('user_agent');
        $table->json('metadata')->nullable();
        $table->string('identifier_hash')->nullable();
        $table->timestamp('created_at')->nullable();
    });

    $this->from(route('login'))
        ->post(route('login'), [
            'email' => 'missing-login-probe@example.invalid',
            'password' => 'wrong-password',
        ])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();

    $auditLog = DB::table('auth_audit_logs')
        ->where('event_type', 'login_failure')
        ->first();

    expect($auditLog)->not->toBeNull()
        ->and($auditLog->identifier_hash)->toBe(hash('sha256', 'missing-login-probe@example.invalid'));

    $metadata = json_decode((string) $auditLog->metadata, true);

    expect($metadata)->toMatchArray([
        'identifier_type' => 'email',
        'failure_reason' => 'invalid_credentials',
        'identifier_hash' => hash('sha256', 'missing-login-probe@example.invalid'),
    ]);
});
