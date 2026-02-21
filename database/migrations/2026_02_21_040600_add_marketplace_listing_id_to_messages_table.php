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
        if (Schema::hasColumn('messages', 'marketplace_listing_id')) {
            return;
        }

        Schema::table('messages', function (Blueprint $table): void {
            $table->foreignId('marketplace_listing_id')
                ->nullable()
                ->after('recipient_user_id')
                ->constrained('marketplace_listings')
                ->nullOnDelete();

            $table->index(['marketplace_listing_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('messages', 'marketplace_listing_id')) {
            return;
        }

        Schema::table('messages', function (Blueprint $table): void {
            $table->dropIndex('messages_marketplace_listing_id_created_at_index');
            $table->dropConstrainedForeignId('marketplace_listing_id');
        });
    }
};
