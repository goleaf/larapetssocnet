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
        Schema::create('profile_views', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('profile_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('viewer_user_id')->constrained('users')->cascadeOnDelete();
            $table->date('viewed_on');
            $table->timestamps();

            $table->unique(['profile_user_id', 'viewer_user_id', 'viewed_on'], 'profile_views_unique_daily_viewer');
            $table->index(['profile_user_id', 'viewed_on'], 'profile_views_profile_date_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profile_views');
    }
};
