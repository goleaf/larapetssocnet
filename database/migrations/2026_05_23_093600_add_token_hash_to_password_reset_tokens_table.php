<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('password_reset_tokens', 'token_hash')) {
            Schema::table('password_reset_tokens', function (Blueprint $table): void {
                $table->string('token_hash', 64)->nullable()->after('token')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('password_reset_tokens', 'token_hash')) {
            Schema::table('password_reset_tokens', function (Blueprint $table): void {
                $table->dropIndex('password_reset_tokens_token_hash_index');
                $table->dropColumn('token_hash');
            });
        }
    }
};
