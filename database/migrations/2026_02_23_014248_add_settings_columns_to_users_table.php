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
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('password_changed_at')->nullable();

            // Privacy settings
            $table->string('profile_visibility')->default('public');
            $table->string('messaging_permission')->default('everyone');
            $table->string('pets_visibility')->default('everyone');
            $table->string('groups_visibility')->default('everyone');

            $table->boolean('show_in_explore')->default(true);
            $table->boolean('open_following')->default(true);

            $table->json('notification_preferences')->nullable();

            // Account deletion
            $table->timestamp('scheduled_deletion_at')->nullable();
            $table->string('deletion_reason')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'password_changed_at',
                'profile_visibility',
                'messaging_permission',
                'pets_visibility',
                'groups_visibility',
                'show_in_explore',
                'open_following',
                'notification_preferences',
                'scheduled_deletion_at',
                'deletion_reason',
            ]);
        });
    }
};
