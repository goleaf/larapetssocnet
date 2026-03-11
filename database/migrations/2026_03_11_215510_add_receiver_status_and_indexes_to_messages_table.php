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
        if (! Schema::hasTable('messages')) {
            return;
        }

        Schema::table('messages', function (Blueprint $table): void {
            if (! Schema::hasColumn('messages', 'receiver_id')) {
                $table->foreignId('receiver_id')
                    ->nullable()
                    ->after('sender_id')
                    ->constrained('users')
                    ->cascadeOnDelete();
            }

            if (! Schema::hasColumn('messages', 'status')) {
                $table->string('status')->default('sent')->after('body');
            }

            if (! Schema::hasIndex('messages', ['sender_id', 'receiver_id', 'created_at'])) {
                $table->index(['sender_id', 'receiver_id', 'created_at']);
            }

            if (! Schema::hasIndex('messages', ['receiver_id', 'read_at'])) {
                $table->index(['receiver_id', 'read_at']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('messages')) {
            return;
        }

        Schema::table('messages', function (Blueprint $table): void {
            if (Schema::hasIndex('messages', ['sender_id', 'receiver_id', 'created_at'])) {
                $table->dropIndex('messages_sender_id_receiver_id_created_at_index');
            }

            if (Schema::hasIndex('messages', ['receiver_id', 'read_at'])) {
                $table->dropIndex('messages_receiver_id_read_at_index');
            }

            if (Schema::hasColumn('messages', 'status')) {
                $table->dropColumn('status');
            }

            if (Schema::hasColumn('messages', 'receiver_id')) {
                $table->dropConstrainedForeignId('receiver_id');
            }
        });
    }
};
