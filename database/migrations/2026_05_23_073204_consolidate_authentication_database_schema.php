<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const USERNAME_CASE_INSENSITIVE_INDEX = 'users_username_case_insensitive_unique';

    private const RESERVED_USERNAME_CASE_INSENSITIVE_INDEX = 'reserved_usernames_username_case_insensitive_unique';

    private const SESSIONS_USER_ACTIVITY_INDEX = 'sessions_user_id_last_activity_index';

    private const AUTH_AUDIT_FAILURE_LOOKUP_INDEX = 'auth_audit_logs_failure_lookup_index';

    /**
     * @var list<string>
     */
    private array $accountStatuses = [
        'active',
        'deactivated',
        'suspended',
        'pending-deletion',
    ];

    /**
     * @var list<string>
     */
    private array $auditEvents = [
        'account_deletion_cancelled',
        'account_deletion_scheduled',
        'account_reactivated',
        'email_verified',
        'login_failure',
        'login_anomaly_dismissed',
        'login_anomaly_secured',
        'login_restricted',
        'login_success',
        'logout',
        'magic_link_accepted',
        'magic_link_rejected',
        'magic_link_requested',
        'magic_link_restricted',
        'other_sessions_logged_out',
        'password_change',
        'password_reset',
        'password_reset_requested',
        'profile_avatar_updated',
        'profile_cover_position_updated',
        'profile_cover_updated',
        'profile_privacy_setting_updated',
        'profile_updated',
        'registration',
        'registration_blocked',
        'registration_honeypot',
        'social_login_rejected',
        'social_login_restricted',
        'social_login_success',
        'two_factor_challenge_failed',
        'two_factor_challenge_passed',
        'two_factor_disabled',
        'two_factor_enabled',
        'verification_email_resent',
        'verification_email_sent',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            $this->addUserAuthenticationColumns();
            $this->normalizeExistingUsernames();
            $this->addUserAuthenticationIndexes();
            $this->backfillAccountStatus();
        }

        if (Schema::hasTable('reserved_usernames')) {
            $this->dropIndexIfExists('reserved_usernames', 'reserved_usernames_username_lower_unique');
            $this->createCaseInsensitiveUniqueIndex('reserved_usernames', 'username', self::RESERVED_USERNAME_CASE_INSENSITIVE_INDEX);
        }

        if (Schema::hasTable('sessions')) {
            Schema::table('sessions', function (Blueprint $table): void {
                if (! $this->hasIndexByName('sessions', self::SESSIONS_USER_ACTIVITY_INDEX)) {
                    $table->index(['user_id', 'last_activity'], self::SESSIONS_USER_ACTIVITY_INDEX);
                }
            });
        }

        $this->rebuildAuthAuditLogsTable();
        $this->rebuildSocialAccountsTable();
        $this->rebuildMagicLinkTokensTable();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('magic_link_tokens');
        Schema::dropIfExists('social_accounts');
        Schema::dropIfExists('auth_audit_logs');

        if (Schema::hasTable('sessions')) {
            Schema::table('sessions', function (Blueprint $table): void {
                if ($this->hasIndexByName('sessions', self::SESSIONS_USER_ACTIVITY_INDEX)) {
                    $table->dropIndex(self::SESSIONS_USER_ACTIVITY_INDEX);
                }
            });
        }

        if (Schema::hasTable('reserved_usernames')) {
            $this->dropIndexIfExists('reserved_usernames', self::RESERVED_USERNAME_CASE_INSENSITIVE_INDEX);
        }

        if (! Schema::hasTable('users')) {
            return;
        }

        $this->dropIndexIfExists('users', self::USERNAME_CASE_INSENSITIVE_INDEX);

        Schema::table('users', function (Blueprint $table): void {
            foreach ([
                'pending_email',
                'two_factor_secret',
                'two_factor_recovery_codes',
                'terms_accepted_at',
                'terms_version',
                'registration_ip_address',
                'registration_user_agent',
                'profile_completeness_score',
                'account_status',
                'last_active_at',
                'last_login_at',
                'failed_login_attempts',
                'last_failed_login_at',
                'username_change_allowed_at',
                'deactivated_at',
                'deactivation_reason',
                'suspended_until',
                'suspension_reason',
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function addUserAuthenticationColumns(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'username')) {
                $table->string('username', 30)->nullable();
            }

            if (! Schema::hasColumn('users', 'birth_date')) {
                $table->date('birth_date')->nullable();
            }

            if (! Schema::hasColumn('users', 'pending_email')) {
                $table->string('pending_email')->nullable();
            }

            if (! Schema::hasColumn('users', 'two_factor_secret')) {
                $table->text('two_factor_secret')->nullable();
            }

            if (! Schema::hasColumn('users', 'two_factor_recovery_codes')) {
                $table->text('two_factor_recovery_codes')->nullable();
            }

            if (! Schema::hasColumn('users', 'terms_accepted_at')) {
                $table->timestamp('terms_accepted_at')->nullable();
            }

            if (! Schema::hasColumn('users', 'terms_version')) {
                $table->string('terms_version', 32)->nullable();
            }

            if (! Schema::hasColumn('users', 'registration_ip_address')) {
                $table->string('registration_ip_address', 45)->nullable();
            }

            if (! Schema::hasColumn('users', 'registration_user_agent')) {
                $table->text('registration_user_agent')->nullable();
            }

            if (! Schema::hasColumn('users', 'profile_completeness_score')) {
                $table->unsignedTinyInteger('profile_completeness_score')->default(0);
            }

            if (! Schema::hasColumn('users', 'account_status')) {
                $table->enum('account_status', $this->accountStatuses)->default('active');
            }

            if (! Schema::hasColumn('users', 'last_active_at')) {
                $table->timestamp('last_active_at')->nullable();
            }

            if (! Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable();
            }

            if (! Schema::hasColumn('users', 'failed_login_attempts')) {
                $table->unsignedSmallInteger('failed_login_attempts')->default(0);
            }

            if (! Schema::hasColumn('users', 'last_failed_login_at')) {
                $table->timestamp('last_failed_login_at')->nullable();
            }

            if (! Schema::hasColumn('users', 'username_change_allowed_at')) {
                $table->timestamp('username_change_allowed_at')->nullable();
            }

            if (! Schema::hasColumn('users', 'deactivated_at')) {
                $table->timestamp('deactivated_at')->nullable();
            }

            if (! Schema::hasColumn('users', 'deactivation_reason')) {
                $table->string('deactivation_reason')->nullable();
            }

            if (! Schema::hasColumn('users', 'suspended_until')) {
                $table->timestamp('suspended_until')->nullable();
            }

            if (! Schema::hasColumn('users', 'suspension_reason')) {
                $table->string('suspension_reason')->nullable();
            }
        });

        if (Schema::hasColumn('users', 'last_seen_at') && Schema::hasColumn('users', 'last_active_at')) {
            DB::table('users')
                ->whereNull('last_active_at')
                ->whereNotNull('last_seen_at')
                ->update(['last_active_at' => DB::raw('last_seen_at')]);
        }

        if (Schema::hasColumn('users', 'username_changed_at') && Schema::hasColumn('users', 'username_change_allowed_at')) {
            DB::table('users')
                ->whereNull('username_change_allowed_at')
                ->whereNotNull('username_changed_at')
                ->update(['username_change_allowed_at' => DB::raw('username_changed_at')]);
        }
    }

    private function addUserAuthenticationIndexes(): void
    {
        foreach (['users_username_unique', 'users_username_lower_unique'] as $index) {
            $this->dropIndexIfExists('users', $index);
        }

        if (Schema::hasColumn('users', 'username')) {
            $this->createCaseInsensitiveUniqueIndex('users', 'username', self::USERNAME_CASE_INSENSITIVE_INDEX);
        }

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'pending_email') && ! $this->hasIndexByName('users', 'users_pending_email_unique')) {
                $table->unique('pending_email', 'users_pending_email_unique');
            }

            if (Schema::hasColumn('users', 'account_status') && ! $this->hasIndexByName('users', 'users_account_status_index')) {
                $table->index('account_status', 'users_account_status_index');
            }

            if (Schema::hasColumn('users', 'last_active_at') && ! $this->hasIndexByName('users', 'users_last_active_at_index')) {
                $table->index('last_active_at', 'users_last_active_at_index');
            }

            if (Schema::hasColumn('users', 'last_login_at') && ! $this->hasIndexByName('users', 'users_last_login_at_index')) {
                $table->index('last_login_at', 'users_last_login_at_index');
            }

            if (Schema::hasColumn('users', 'last_failed_login_at') && ! $this->hasIndexByName('users', 'users_last_failed_login_at_index')) {
                $table->index('last_failed_login_at', 'users_last_failed_login_at_index');
            }

            if (Schema::hasColumn('users', 'deactivated_at') && ! $this->hasIndexByName('users', 'users_deactivated_at_index')) {
                $table->index('deactivated_at', 'users_deactivated_at_index');
            }

            if (Schema::hasColumn('users', 'suspended_until') && ! $this->hasIndexByName('users', 'users_suspended_until_index')) {
                $table->index('suspended_until', 'users_suspended_until_index');
            }
        });
    }

    private function normalizeExistingUsernames(): void
    {
        if (! Schema::hasColumn('users', 'username')) {
            return;
        }

        /** @var Collection<int, object{id: int, username: string|null}> $users */
        $users = DB::table('users')
            ->select(['id', 'username'])
            ->whereNotNull('username')
            ->orderBy('id')
            ->get();

        $seen = [];

        foreach ($users as $user) {
            $normalized = $this->normalizeUsername((string) $user->username);

            if ($normalized === '') {
                $normalized = 'user_'.$user->id;
            }

            $candidate = Str::limit($normalized, 30, '');
            $suffix = 1;

            while (isset($seen[$candidate])) {
                $suffixText = (string) $suffix++;
                $candidate = Str::limit($normalized, 29 - strlen($suffixText), '').'_'.$suffixText;
            }

            $seen[$candidate] = true;

            if ($candidate !== $user->username) {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['username' => $candidate]);
            }
        }
    }

    private function normalizeUsername(string $username): string
    {
        $username = Str::lower(trim($username));
        $username = preg_replace('/[^a-z0-9_-]+/', '_', $username) ?? '';
        $username = preg_replace('/[_-]{2,}/', '_', $username) ?? '';

        return trim($username, '_-');
    }

    private function backfillAccountStatus(): void
    {
        if (! Schema::hasColumn('users', 'account_status')) {
            return;
        }

        DB::table('users')
            ->where('account_status', 'pending_deletion')
            ->update(['account_status' => 'pending-deletion']);

        if (Schema::hasColumn('users', 'suspended_until')) {
            DB::table('users')
                ->whereNotNull('suspended_until')
                ->where('suspended_until', '>', now()->toDateTimeString())
                ->update(['account_status' => 'suspended']);
        }

        if (Schema::hasColumn('users', 'deactivated_at')) {
            DB::table('users')
                ->whereNotNull('deactivated_at')
                ->update(['account_status' => 'deactivated']);
        }

        if (Schema::hasColumn('users', 'scheduled_deletion_at')) {
            DB::table('users')
                ->whereNotNull('scheduled_deletion_at')
                ->update(['account_status' => 'pending-deletion']);
        }
    }

    private function rebuildAuthAuditLogsTable(): void
    {
        if (Schema::hasTable('auth_audit_logs')) {
            Schema::dropIfExists('auth_audit_logs_legacy_auth');
            Schema::rename('auth_audit_logs', 'auth_audit_logs_legacy_auth');
        }

        Schema::create('auth_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('event_type', $this->auditEvents);
            $table->string('ip_address', 45);
            $table->text('user_agent');
            $table->string('country')->nullable();
            $table->string('city')->nullable();
            $table->json('additional_data')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at'], 'auth_audit_logs_user_created_at_index');
            $table->index('created_at', 'auth_audit_logs_created_at_index');
        });

        $this->addAuthAuditFailureLookupIndex();

        if (! Schema::hasTable('auth_audit_logs_legacy_auth')) {
            return;
        }

        $hasIdentifierHash = Schema::hasColumn('auth_audit_logs_legacy_auth', 'identifier_hash');

        DB::table('auth_audit_logs_legacy_auth')
            ->select(['id', 'user_id', 'event_type', 'ip_address', 'user_agent', 'metadata', 'created_at'])
            ->orderBy('id')
            ->chunkById(500, function (Collection $logs) use ($hasIdentifierHash): void {
                foreach ($logs as $log) {
                    $additionalData = $log->metadata;

                    if ($hasIdentifierHash) {
                        $identifierHash = DB::table('auth_audit_logs_legacy_auth')
                            ->where('id', $log->id)
                            ->value('identifier_hash');

                        if (is_string($identifierHash) && $identifierHash !== '') {
                            $decoded = is_string($additionalData) && $additionalData !== ''
                                ? json_decode($additionalData, true)
                                : [];

                            $decoded = is_array($decoded) ? $decoded : [];
                            $decoded['identifier_hash'] = $identifierHash;
                            $additionalData = json_encode($decoded);
                        }
                    }

                    DB::table('auth_audit_logs')->insert([
                        'id' => $log->id,
                        'user_id' => $log->user_id,
                        'event_type' => in_array($log->event_type, $this->auditEvents, true) ? $log->event_type : 'login_failure',
                        'ip_address' => $log->ip_address ?: '',
                        'user_agent' => $log->user_agent ?: '',
                        'country' => null,
                        'city' => null,
                        'additional_data' => $additionalData,
                        'created_at' => $log->created_at,
                    ]);
                }
            }, 'id');

        Schema::dropIfExists('auth_audit_logs_legacy_auth');
    }

    private function addAuthAuditFailureLookupIndex(): void
    {
        if ($this->hasIndexByName('auth_audit_logs', self::AUTH_AUDIT_FAILURE_LOOKUP_INDEX)) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::statement('CREATE INDEX '.self::AUTH_AUDIT_FAILURE_LOOKUP_INDEX." ON auth_audit_logs (event_type, ip_address, json_extract(additional_data, '$.identifier_hash'), created_at)");

            return;
        }

        Schema::table('auth_audit_logs', function (Blueprint $table): void {
            $table->index(['event_type', 'ip_address', 'created_at'], self::AUTH_AUDIT_FAILURE_LOOKUP_INDEX);
        });
    }

    private function rebuildSocialAccountsTable(): void
    {
        if (Schema::hasTable('social_accounts')) {
            Schema::dropIfExists('social_accounts_legacy_auth');
            Schema::rename('social_accounts', 'social_accounts_legacy_auth');
        }

        Schema::create('social_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('provider', 40);
            $table->string('provider_user_id', 191);
            $table->text('provider_token')->nullable();
            $table->timestamp('provider_token_expires_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_user_id'], 'social_accounts_provider_user_unique');
            $table->index(['user_id', 'provider'], 'social_accounts_user_provider_index');
        });

        if (! Schema::hasTable('social_accounts_legacy_auth')) {
            return;
        }

        DB::table('social_accounts_legacy_auth')
            ->select(['id', 'user_id', 'provider', 'provider_id', 'token', 'expires_at', 'created_at', 'updated_at'])
            ->orderBy('id')
            ->chunkById(500, function (Collection $accounts): void {
                foreach ($accounts as $account) {
                    DB::table('social_accounts')->insert([
                        'id' => $account->id,
                        'user_id' => $account->user_id,
                        'provider' => $account->provider,
                        'provider_user_id' => $account->provider_id,
                        'provider_token' => $account->token,
                        'provider_token_expires_at' => $account->expires_at,
                        'created_at' => $account->created_at,
                        'updated_at' => $account->updated_at,
                    ]);
                }
            }, 'id');

        Schema::dropIfExists('social_accounts_legacy_auth');
    }

    private function rebuildMagicLinkTokensTable(): void
    {
        if (Schema::hasTable('magic_login_tokens')) {
            Schema::dropIfExists('magic_link_tokens_legacy_auth');
            Schema::rename('magic_login_tokens', 'magic_link_tokens_legacy_auth');
        } elseif (Schema::hasTable('magic_link_tokens')) {
            Schema::dropIfExists('magic_link_tokens_legacy_auth');
            Schema::rename('magic_link_tokens', 'magic_link_tokens_legacy_auth');
        }

        Schema::create('magic_link_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('token', 80);
            $table->string('token_hash', 64);
            $table->timestamp('used_at')->nullable();
            $table->timestamp('expires_at');

            $table->index('token_hash', 'magic_link_tokens_token_hash_index');
            $table->index(['user_id', 'expires_at'], 'magic_link_tokens_user_expires_index');
        });

        if (! Schema::hasTable('magic_link_tokens_legacy_auth')) {
            return;
        }

        DB::table('magic_link_tokens_legacy_auth')
            ->select(['id', 'user_id', 'public_id', 'token_hash', 'expires_at', 'consumed_at'])
            ->orderBy('id')
            ->chunkById(500, function (Collection $tokens): void {
                foreach ($tokens as $token) {
                    DB::table('magic_link_tokens')->insert([
                        'id' => $token->id,
                        'user_id' => $token->user_id,
                        'token' => $token->public_id,
                        'token_hash' => $token->token_hash,
                        'used_at' => $token->consumed_at,
                        'expires_at' => $token->expires_at,
                    ]);
                }
            }, 'id');

        Schema::dropIfExists('magic_link_tokens_legacy_auth');
    }

    private function createCaseInsensitiveUniqueIndex(string $table, string $column, string $index): void
    {
        if ($this->hasIndexByName($table, $index)) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::statement("CREATE UNIQUE INDEX {$index} ON {$table} ({$column} COLLATE NOCASE) WHERE {$column} IS NOT NULL");

            return;
        }

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("CREATE UNIQUE INDEX {$index} ON {$table} ({$column} COLLATE utf8mb4_unicode_ci)");

            return;
        }

        DB::statement("CREATE UNIQUE INDEX {$index} ON {$table} (lower({$column})) WHERE {$column} IS NOT NULL");
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        if (! $this->hasIndexByName($table, $index)) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE {$table} DROP INDEX {$index}");

            return;
        }

        DB::statement("DROP INDEX IF EXISTS {$index}");
    }

    private function hasIndexByName(string $table, string $name): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn (array $index): bool => ($index['name'] ?? null) === $name);
    }
};
