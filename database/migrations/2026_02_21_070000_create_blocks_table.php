<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('blocks')) {
            Schema::create('blocks', function (Blueprint $table): void {
                $table->foreignId('blocker_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('blocked_id')->constrained('users')->cascadeOnDelete();
                $table->timestamp('created_at')->useCurrent();

                $table->primary(['blocker_id', 'blocked_id']);
                $table->index('blocked_id');
            });
        }

        if (Schema::hasTable('user_blocks') && Schema::hasTable('blocks')) {
            $rows = DB::table('user_blocks')->get(['blocker_id', 'blocked_id', 'created_at']);

            foreach ($rows as $row) {
                DB::table('blocks')->updateOrInsert(
                    ['blocker_id' => $row->blocker_id, 'blocked_id' => $row->blocked_id],
                    ['created_at' => $row->created_at ?? now()]
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('blocks');
    }
};
