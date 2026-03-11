<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pets', function (Blueprint $table): void {
            $table->index(['user_id', 'is_public', 'created_at'], 'pets_user_id_is_public_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('pets', function (Blueprint $table): void {
            $table->dropIndex('pets_user_id_is_public_created_at_index');
        });
    }
};
