<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('posts') && ! Schema::hasColumn('posts', 'is_fanned_out')) {
            Schema::table('posts', function (Blueprint $table): void {
                $table->boolean('is_fanned_out')->default(false)->after('is_system_generated');
            });

            DB::table('posts')
                ->where('status', 'published')
                ->update(['is_fanned_out' => true]);
        }

        if (Schema::hasTable('post_drafts') && ! Schema::hasColumn('post_drafts', 'state_hash')) {
            Schema::table('post_drafts', function (Blueprint $table): void {
                $table->string('state_hash', 64)->nullable()->after('state');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('post_drafts') && Schema::hasColumn('post_drafts', 'state_hash')) {
            Schema::table('post_drafts', function (Blueprint $table): void {
                $table->dropColumn('state_hash');
            });
        }

        if (Schema::hasTable('posts') && Schema::hasColumn('posts', 'is_fanned_out')) {
            Schema::table('posts', function (Blueprint $table): void {
                $table->dropColumn('is_fanned_out');
            });
        }
    }
};
