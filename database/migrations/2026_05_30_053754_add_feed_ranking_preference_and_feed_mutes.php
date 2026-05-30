<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'feed_ranking_preference')) {
                $table->string('feed_ranking_preference', 20)->default('latest')->index();
            }
        });

        if (! Schema::hasTable('feed_mutes')) {
            Schema::create('feed_mutes', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->morphs('mutable');
                $table->timestamps();

                $table->unique(['user_id', 'mutable_type', 'mutable_id'], 'feed_mutes_user_mutable_unique');
                $table->index(['user_id', 'created_at'], 'feed_mutes_user_created_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_mutes');

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'feed_ranking_preference')) {
                $table->dropColumn('feed_ranking_preference');
            }
        });
    }
};
