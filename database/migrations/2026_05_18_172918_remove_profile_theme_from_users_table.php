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
        if (! Schema::hasColumn('users', 'profile_theme')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('profile_theme');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('users', 'profile_theme')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->string('profile_theme', 32)->nullable()->after('social_links');
        });
    }
};
