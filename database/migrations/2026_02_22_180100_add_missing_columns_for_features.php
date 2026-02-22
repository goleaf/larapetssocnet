<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Users: role + soft deletes
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'role')) {
                $table->string('role', 20)->default('member')->after('is_banned');
            }
            if (! Schema::hasColumn('users', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        // Badges: add color + type columns
        Schema::table('badges', function (Blueprint $table) {
            if (! Schema::hasColumn('badges', 'color')) {
                $table->string('color', 20)->default('emerald')->after('icon');
            }
            if (! Schema::hasColumn('badges', 'type')) {
                $table->string('type', 10)->default('auto')->after('color');
            }
        });

        // Contest entries: is_winner + soft deletes
        Schema::table('contest_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('contest_entries', 'is_winner')) {
                $table->boolean('is_winner')->default(false)->after('votes_count');
            }
            if (! Schema::hasColumn('contest_entries', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        // Contest votes: contest_id
        Schema::table('contest_votes', function (Blueprint $table) {
            if (! Schema::hasColumn('contest_votes', 'contest_id')) {
                $table->unsignedBigInteger('contest_id')->nullable()->after('id');
                $table->foreign('contest_id')->references('id')->on('contests')->cascadeOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role']);
            $table->dropSoftDeletes();
        });

        Schema::table('badges', function (Blueprint $table) {
            $table->dropColumn(['color', 'type']);
        });

        Schema::table('contest_entries', function (Blueprint $table) {
            $table->dropColumn(['is_winner']);
            $table->dropSoftDeletes();
        });

        Schema::table('contest_votes', function (Blueprint $table) {
            $table->dropForeign(['contest_id']);
            $table->dropColumn(['contest_id']);
        });
    }
};
