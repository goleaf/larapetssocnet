<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table): void {
            if (! Schema::hasColumn('groups', 'type')) {
                $table->string('type', 10)->nullable()->after('privacy');
            }

            if (! Schema::hasColumn('groups', 'location')) {
                $table->string('location', 100)->nullable()->after('rules');
            }

            if (! Schema::hasColumn('groups', 'website')) {
                $table->string('website', 200)->nullable()->after('location');
            }
        });

        Schema::table('group_members', function (Blueprint $table): void {
            if (! Schema::hasColumn('group_members', 'invited_by')) {
                $table->foreignId('invited_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            }
        });

        Schema::table('posts', function (Blueprint $table): void {
            if (! Schema::hasColumn('posts', 'group_id')) {
                $table->foreignId('group_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
                $table->index('group_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            if (Schema::hasColumn('posts', 'group_id')) {
                $table->dropForeign(['group_id']);
                $table->dropIndex(['group_id']);
                $table->dropColumn('group_id');
            }
        });

        Schema::table('group_members', function (Blueprint $table): void {
            if (Schema::hasColumn('group_members', 'invited_by')) {
                $table->dropForeign(['invited_by']);
                $table->dropColumn('invited_by');
            }
        });

        Schema::table('groups', function (Blueprint $table): void {
            foreach (['website', 'location', 'type'] as $column) {
                if (Schema::hasColumn('groups', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
