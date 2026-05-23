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
        Schema::create('pet_owners', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pet_id')->constrained('pets')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('invited_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('role')->default('co_owner');
            $table->boolean('can_post')->default(false);
            $table->boolean('can_edit')->default(false);
            $table->boolean('can_manage_health')->default(false);
            $table->boolean('can_manage_gallery')->default(false);
            $table->boolean('can_manage_adoption')->default(false);
            $table->boolean('can_delete')->default(false);
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->unique(['pet_id', 'user_id']);
            $table->index(['user_id', 'accepted_at']);
            $table->index(['pet_id', 'role']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pet_owners');
    }
};
