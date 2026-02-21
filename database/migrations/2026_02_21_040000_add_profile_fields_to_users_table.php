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
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique();
            $table->text('bio')->nullable();
            $table->string('avatar_path')->nullable();
            $table->string('city')->nullable();
            $table->string('country_code', 2)->nullable();
            $table->boolean('is_private')->default(false);
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->string('onboarding_step')->default('welcome');
            $table->text('interests_text')->nullable();
            $table->unsignedInteger('followers_count')->default(0);
            $table->unsignedInteger('following_count')->default(0);
            $table->unsignedInteger('pets_count')->default(0);
            $table->unsignedInteger('posts_count')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'username',
                'bio',
                'avatar_path',
                'city',
                'country_code',
                'is_private',
                'last_seen_at',
                'onboarding_step',
                'interests_text',
                'followers_count',
                'following_count',
                'pets_count',
                'posts_count',
            ]);
        });
    }
};
