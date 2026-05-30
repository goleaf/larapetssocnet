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
        Schema::table('profile_views', function (Blueprint $table): void {
            if (! Schema::hasColumn('profile_views', 'views_count')) {
                $table->unsignedInteger('views_count')->default(1)->after('viewed_on');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('profile_views', function (Blueprint $table): void {
            if (Schema::hasColumn('profile_views', 'views_count')) {
                $table->dropColumn('views_count');
            }
        });
    }
};
