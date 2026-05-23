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
        Schema::table('reactions', function (Blueprint $table): void {
            $table->index(['reactable_type', 'created_at', 'reactable_id'], 'reactions_wrapped_type_date_reactable_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reactions', function (Blueprint $table): void {
            $table->dropIndex('reactions_wrapped_type_date_reactable_index');
        });
    }
};
