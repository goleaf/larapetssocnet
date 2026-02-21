<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'username')) {
                $table->string('username', 30)->nullable();
            }

            if (! Schema::hasColumn('users', 'bio_html')) {
                $table->text('bio_html')->nullable();
            }

            if (! Schema::hasColumn('users', 'website')) {
                $table->string('website')->nullable();
            }

            if (! Schema::hasColumn('users', 'location')) {
                $table->string('location', 120)->nullable();
            }

            if (! Schema::hasColumn('users', 'location_lat')) {
                $table->string('location_lat', 32)->nullable();
            }

            if (! Schema::hasColumn('users', 'location_lng')) {
                $table->string('location_lng', 32)->nullable();
            }

            if (! Schema::hasColumn('users', 'gender')) {
                $table->string('gender', 32)->nullable();
            }

            if (! Schema::hasColumn('users', 'gender_custom')) {
                $table->string('gender_custom', 120)->nullable();
            }

            if (! Schema::hasColumn('users', 'birthdate')) {
                $table->date('birthdate')->nullable();
            }

            if (! Schema::hasColumn('users', 'birth_date')) {
                $table->date('birth_date')->nullable();
            }

            if (! Schema::hasColumn('users', 'flags')) {
                $table->text('flags')->nullable();
            }

            if (! Schema::hasColumn('users', 'is_banned')) {
                $table->boolean('is_banned')->default(false);
            }

            if (! Schema::hasColumn('users', 'ban_reason')) {
                $table->text('ban_reason')->nullable();
            }

            if (! Schema::hasColumn('users', 'privacy_display_email')) {
                $table->boolean('privacy_display_email')->default(false);
            }

            if (! Schema::hasColumn('users', 'privacy_display_location')) {
                $table->boolean('privacy_display_location')->default(true);
            }

            if (! Schema::hasColumn('users', 'privacy_display_birthdate')) {
                $table->boolean('privacy_display_birthdate')->default(false);
            }

            if (! Schema::hasColumn('users', 'privacy_display_last_seen')) {
                $table->boolean('privacy_display_last_seen')->default(true);
            }

            if (! Schema::hasColumn('users', 'last_seen_at')) {
                $table->timestamp('last_seen_at')->nullable();
            }

            if (! Schema::hasColumn('users', 'followers_count')) {
                $table->unsignedInteger('followers_count')->default(0);
            }

            if (! Schema::hasColumn('users', 'following_count')) {
                $table->unsignedInteger('following_count')->default(0);
            }

            if (! Schema::hasColumn('users', 'pets_count')) {
                $table->unsignedInteger('pets_count')->default(0);
            }

            if (! Schema::hasColumn('users', 'posts_count')) {
                $table->unsignedInteger('posts_count')->default(0);
            }

            if (! Schema::hasColumn('users', 'following_pets_count')) {
                $table->unsignedInteger('following_pets_count')->default(0);
            }

            if (! Schema::hasColumn('users', 'blocked_users_count')) {
                $table->unsignedInteger('blocked_users_count')->default(0);
            }

            if (! Schema::hasColumn('users', 'blocked_by_count')) {
                $table->unsignedInteger('blocked_by_count')->default(0);
            }

            if (! Schema::hasColumn('users', 'onboarding_completed_at')) {
                $table->timestamp('onboarding_completed_at')->nullable();
            }

            if (! Schema::hasColumn('users', 'cover_photo_path')) {
                $table->string('cover_photo_path')->nullable();
            }

            if (! Schema::hasColumn('users', 'profile_photo_path')) {
                $table->string('profile_photo_path')->nullable();
            }
        });

        if (Schema::hasColumn('users', 'username') && ! Schema::hasIndex('users', ['username'], 'unique')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unique('username');
            });
        }

        if (Schema::hasColumn('users', 'username') && ! Schema::hasIndex('users', ['username'])) {
            Schema::table('users', function (Blueprint $table) {
                $table->index('username');
            });
        }

        if (Schema::hasColumn('users', 'last_seen_at') && ! Schema::hasIndex('users', ['last_seen_at'])) {
            Schema::table('users', function (Blueprint $table) {
                $table->index('last_seen_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        foreach ([
            'bio_html',
            'website',
            'location',
            'location_lat',
            'location_lng',
            'gender',
            'gender_custom',
            'birthdate',
            'birth_date',
            'flags',
            'is_banned',
            'ban_reason',
            'privacy_display_email',
            'privacy_display_location',
            'privacy_display_birthdate',
            'privacy_display_last_seen',
            'following_pets_count',
            'blocked_users_count',
            'blocked_by_count',
            'onboarding_completed_at',
            'cover_photo_path',
            'profile_photo_path',
        ] as $column) {
            $this->dropColumnIfExists('users', $column);
        }
    }

    private function dropColumnIfExists(string $table, string $column): void
    {
        if (! Schema::hasColumn($table, $column)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column) {
            $blueprint->dropColumn($column);
        });
    }
};
