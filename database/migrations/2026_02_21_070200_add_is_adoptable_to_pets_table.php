<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pets', function (Blueprint $table): void {
            if (! Schema::hasColumn('pets', 'is_adoptable')) {
                $table->boolean('is_adoptable')->default(false)->after('is_public');
                $table->index(['is_adoptable', 'created_at']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('pets', function (Blueprint $table): void {
            if (Schema::hasColumn('pets', 'is_adoptable')) {
                $table->dropIndex(['is_adoptable', 'created_at']);
                $table->dropColumn('is_adoptable');
            }
        });
    }
};

