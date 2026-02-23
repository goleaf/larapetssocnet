<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pet_health_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('pet_health_logs', 'next_due_at')) {
                $table->timestamp('next_due_at')->nullable()->after('logged_at');
                $table->index(['pet_id', 'next_due_at']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('pet_health_logs', function (Blueprint $table): void {
            if (Schema::hasColumn('pet_health_logs', 'next_due_at')) {
                $table->dropIndex(['pet_id', 'next_due_at']);
                $table->dropColumn('next_due_at');
            }
        });
    }
};
