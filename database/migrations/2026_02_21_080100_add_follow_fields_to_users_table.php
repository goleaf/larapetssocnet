<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'followers_count')) {
                $table->unsignedInteger('followers_count')->default(0)->after('pets_count');
                $table->index('followers_count');
            }

            if (! Schema::hasColumn('users', 'following_count')) {
                $table->unsignedInteger('following_count')->default(0)->after('followers_count');
                $table->index('following_count');
            }

            if (! Schema::hasColumn('users', 'follow_requests_count')) {
                $table->unsignedInteger('follow_requests_count')->default(0)->after('following_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'follow_requests_count')) {
                $table->dropColumn('follow_requests_count');
            }
        });
    }
};
