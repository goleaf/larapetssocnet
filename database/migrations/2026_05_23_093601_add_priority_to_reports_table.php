<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('reports', 'priority')) {
            Schema::table('reports', function (Blueprint $table): void {
                $table->string('priority')->default('normal')->after('status');
                $table->index(['priority', 'status', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('reports', 'priority')) {
            Schema::table('reports', function (Blueprint $table): void {
                $table->dropIndex('reports_priority_status_created_at_index');
                $table->dropColumn('priority');
            });
        }
    }
};
