<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pets', function (Blueprint $table): void {
            if (! Schema::hasColumn('pets', 'personality_tags')) {
                $table->json('personality_tags')->nullable()->after('bio');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pets', function (Blueprint $table): void {
            if (Schema::hasColumn('pets', 'personality_tags')) {
                $table->dropColumn('personality_tags');
            }
        });
    }
};
