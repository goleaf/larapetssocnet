<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('follows')) {
            Schema::create('follows', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('follower_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('following_id')->constrained('users')->cascadeOnDelete();
                $table->string('status')->default('accepted');
                $table->timestamp('created_at')->useCurrent();

                $table->unique(['follower_id', 'following_id']);
                $table->index(['following_id', 'status']);
                $table->index(['follower_id', 'status']);
                $table->index(['follower_id', 'following_id', 'status']);
            });
        }

        if (Schema::hasTable('user_follows') && Schema::hasTable('follows')) {
            $legacyRows = DB::table('user_follows')->get(['follower_id', 'following_id', 'created_at']);

            foreach ($legacyRows as $row) {
                DB::table('follows')->updateOrInsert(
                    [
                        'follower_id' => $row->follower_id,
                        'following_id' => $row->following_id,
                    ],
                    [
                        'status' => 'accepted',
                        'created_at' => $row->created_at ?? now(),
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('follows');
    }
};
