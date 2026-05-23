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
        Schema::table('pets', function (Blueprint $table): void {
            if (! Schema::hasColumn('pets', 'visibility')) {
                $table->string('visibility', 20)->default('public')->after('is_public');
                $table->index(['visibility', 'created_at']);
            }

            if (! Schema::hasColumn('pets', 'species_other')) {
                $table->string('species_other', 80)->nullable()->after('species');
            }

            if (! Schema::hasColumn('pets', 'spayed_neutered_status')) {
                $table->string('spayed_neutered_status', 20)->default('unknown')->after('is_deceased');
            }

            if (! Schema::hasColumn('pets', 'vaccination_status')) {
                $table->string('vaccination_status', 20)->default('unknown')->after('spayed_neutered_status');
            }

            if (! Schema::hasColumn('pets', 'last_vaccinated_on')) {
                $table->date('last_vaccinated_on')->nullable()->after('vaccination_status');
            }

            if (! Schema::hasColumn('pets', 'microchipped_status')) {
                $table->string('microchipped_status', 20)->default('unknown')->after('last_vaccinated_on');
            }

            if (! Schema::hasColumn('pets', 'cover_photo_position')) {
                $table->decimal('cover_photo_position', 5, 2)->default(50)->after('avatar_path');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pets', function (Blueprint $table): void {
            if (Schema::hasColumn('pets', 'visibility')) {
                $table->dropIndex(['visibility', 'created_at']);
            }

            foreach ([
                'cover_photo_position',
                'microchipped_status',
                'last_vaccinated_on',
                'vaccination_status',
                'spayed_neutered_status',
                'species_other',
                'visibility',
            ] as $column) {
                if (Schema::hasColumn('pets', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
