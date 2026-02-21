<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('follows', 'status')) {
            return;
        }

        Schema::table('follows', function (Blueprint $table): void {
            $table->string('status')->default('accepted')->after('following_id');
            $table->index(['following_id', 'status']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('follows', 'status')) {
            return;
        }

        Schema::table('follows', function (Blueprint $table): void {
            $table->dropIndex(['following_id', 'status']);
            $table->dropColumn('status');
        });
    }
};
