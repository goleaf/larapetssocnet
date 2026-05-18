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
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'is_verified')) {
                $table->boolean('is_verified')->default(false)->index();
            }

            if (! Schema::hasColumn('users', 'cover_photo_position')) {
                $table->decimal('cover_photo_position', 5, 2)->default(50);
            }

            if (! Schema::hasColumn('users', 'profile_completed_at')) {
                $table->timestamp('profile_completed_at')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['is_verified', 'cover_photo_position', 'profile_completed_at'] as $column) {
            if (! Schema::hasColumn('users', $column)) {
                continue;
            }

            Schema::table('users', function (Blueprint $table) use ($column): void {
                $table->dropColumn($column);
            });
        }
    }
};
