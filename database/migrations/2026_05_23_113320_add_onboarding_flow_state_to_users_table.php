<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'onboarding_completed')) {
                $table->boolean('onboarding_completed')->default(false);
            }

            if (! Schema::hasColumn('users', 'onboarding_pet_reminder_pending')) {
                $table->boolean('onboarding_pet_reminder_pending')->default(false);
            }

            if (! Schema::hasColumn('users', 'onboarding_pet_reminder_shown_at')) {
                $table->timestamp('onboarding_pet_reminder_shown_at')->nullable();
            }
        });

        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasIndex('users', 'users_onboarding_completed_index')) {
                $table->index('onboarding_completed', 'users_onboarding_completed_index');
            }

            if (! Schema::hasIndex('users', 'users_onboarding_reminder_index')) {
                $table->index(['onboarding_pet_reminder_pending', 'onboarding_pet_reminder_shown_at'], 'users_onboarding_reminder_index');
            }

            if (! Schema::hasIndex('users', 'users_onboarding_suggestions_index')) {
                $table->index(['account_status', 'show_in_explore', 'is_private', 'followers_count'], 'users_onboarding_suggestions_index');
            }
        });

        if (Schema::hasColumn('users', 'onboarding_completed')) {
            DB::table('users')
                ->where(function ($query): void {
                    $query
                        ->whereNotNull('onboarding_completed_at')
                        ->orWhereIn('onboarding_step', ['completed', 'complete', 'done', '4']);
                })
                ->update(['onboarding_completed' => true]);
        }

        if (Schema::hasTable('social_accounts') && ! Schema::hasColumn('social_accounts', 'provider_avatar_url')) {
            Schema::table('social_accounts', function (Blueprint $table): void {
                $table->string('provider_avatar_url', 2048)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('social_accounts') && Schema::hasColumn('social_accounts', 'provider_avatar_url')) {
            Schema::table('social_accounts', function (Blueprint $table): void {
                $table->dropColumn('provider_avatar_url');
            });
        }

        Schema::table('users', function (Blueprint $table): void {
            foreach (['users_onboarding_suggestions_index', 'users_onboarding_reminder_index', 'users_onboarding_completed_index'] as $index) {
                if (Schema::hasIndex('users', $index)) {
                    $table->dropIndex($index);
                }
            }
        });

        Schema::table('users', function (Blueprint $table): void {
            foreach (['onboarding_pet_reminder_shown_at', 'onboarding_pet_reminder_pending', 'onboarding_completed'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
