<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const USERNAME_LOWER_INDEX = 'users_username_lower_unique';

    private const RESERVED_USERNAME_LOWER_INDEX = 'reserved_usernames_username_lower_unique';

    /**
     * @var list<string>
     */
    private array $accountStatuses = [
        'active',
        'deactivated',
        'suspended',
        'banned',
        'pending_deletion',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table): void {
                if (! Schema::hasColumn('users', 'pending_email')) {
                    $table->string('pending_email')->nullable()->after('email_verified_at');
                }

                if (! Schema::hasColumn('users', 'two_factor_secret')) {
                    $table->text('two_factor_secret')->nullable()->after('remember_token');
                }

                if (! Schema::hasColumn('users', 'two_factor_recovery_codes')) {
                    $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
                }

                if (! Schema::hasColumn('users', 'profile_completeness_score')) {
                    $table->unsignedTinyInteger('profile_completeness_score')->default(0)->after('profile_completed_at');
                }

                if (! Schema::hasColumn('users', 'account_status')) {
                    $table->enum('account_status', $this->accountStatuses)->default('active')->after('is_banned');
                }

                if (! Schema::hasColumn('users', 'failed_login_attempts')) {
                    $table->unsignedInteger('failed_login_attempts')->default(0)->after('last_login_at');
                }

                if (! Schema::hasColumn('users', 'last_failed_login_at')) {
                    $table->timestamp('last_failed_login_at')->nullable()->after('failed_login_attempts');
                }
            });

            $this->addUserIndexes();
            $this->backfillAccountStatus();
        }

        if (Schema::hasTable('reserved_usernames')) {
            $this->createLowerUniqueIndex('reserved_usernames', 'username', self::RESERVED_USERNAME_LOWER_INDEX);
        }

        if (! Schema::hasTable('social_accounts')) {
            Schema::create('social_accounts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('provider', 40);
                $table->string('provider_id', 191);
                $table->string('provider_email')->nullable();
                $table->string('provider_nickname')->nullable();
                $table->string('provider_name')->nullable();
                $table->text('avatar_url')->nullable();
                $table->text('token')->nullable();
                $table->text('refresh_token')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->json('provider_payload')->nullable();
                $table->timestamps();

                $table->unique(['provider', 'provider_id']);
                $table->index(['user_id', 'provider']);
                $table->index('provider_email');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_accounts');

        if (Schema::hasTable('reserved_usernames')) {
            $this->dropLowerUniqueIndex(self::RESERVED_USERNAME_LOWER_INDEX);
        }

        if (! Schema::hasTable('users')) {
            return;
        }

        $this->dropLowerUniqueIndex(self::USERNAME_LOWER_INDEX);

        Schema::table('users', function (Blueprint $table): void {
            if ($this->hasIndexByName('users', 'users_pending_email_unique')) {
                $table->dropUnique('users_pending_email_unique');
            }

            if ($this->hasIndexByName('users', 'users_account_status_index')) {
                $table->dropIndex('users_account_status_index');
            }

            if ($this->hasIndexByName('users', 'users_last_failed_login_at_index')) {
                $table->dropIndex('users_last_failed_login_at_index');
            }

            foreach ([
                'pending_email',
                'two_factor_secret',
                'two_factor_recovery_codes',
                'profile_completeness_score',
                'account_status',
                'failed_login_attempts',
                'last_failed_login_at',
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function addUserIndexes(): void
    {
        if (Schema::hasColumn('users', 'username')) {
            $this->createLowerUniqueIndex('users', 'username', self::USERNAME_LOWER_INDEX);
        }

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'pending_email') && ! $this->hasIndexByName('users', 'users_pending_email_unique')) {
                $table->unique('pending_email', 'users_pending_email_unique');
            }

            if (Schema::hasColumn('users', 'account_status') && ! $this->hasIndexByName('users', 'users_account_status_index')) {
                $table->index('account_status', 'users_account_status_index');
            }

            if (Schema::hasColumn('users', 'last_failed_login_at') && ! $this->hasIndexByName('users', 'users_last_failed_login_at_index')) {
                $table->index('last_failed_login_at', 'users_last_failed_login_at_index');
            }
        });
    }

    private function backfillAccountStatus(): void
    {
        if (! Schema::hasColumn('users', 'account_status')) {
            return;
        }

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
                ->update(['account_status' => 'pending_deletion']);
        }

        if (Schema::hasColumn('users', 'is_banned')) {
            DB::table('users')
                ->where('is_banned', true)
                ->update(['account_status' => 'banned']);
        }
    }

    private function createLowerUniqueIndex(string $table, string $column, string $index): void
    {
        if ($this->hasIndexByName($table, $index)) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::statement("CREATE UNIQUE INDEX {$index} ON {$table} (lower({$column})) WHERE {$column} IS NOT NULL");

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement("CREATE UNIQUE INDEX {$index} ON {$table} (lower({$column})) WHERE {$column} IS NOT NULL");
        }
    }

    private function dropLowerUniqueIndex(string $index): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::statement("DROP INDEX IF EXISTS {$index}");

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement("DROP INDEX IF EXISTS {$index}");
        }
    }

    private function hasIndexByName(string $table, string $name): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn (array $index): bool => ($index['name'] ?? null) === $name);
    }
};
