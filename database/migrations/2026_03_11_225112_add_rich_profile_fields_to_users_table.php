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
            $table->string('display_name', 120)->nullable();
            $table->string('headline', 160)->nullable();
            $table->string('pronouns', 40)->nullable();
            $table->json('social_links')->nullable();
            $table->string('profile_theme', 32)->nullable();
            $table->string('locale', 12)->nullable();
            $table->string('timezone', 64)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'display_name',
                'headline',
                'pronouns',
                'social_links',
                'profile_theme',
                'locale',
                'timezone',
            ]);
        });
    }
};
