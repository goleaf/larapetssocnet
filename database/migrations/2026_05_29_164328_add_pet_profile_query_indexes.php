<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('pets')) {
            DB::statement('CREATE INDEX IF NOT EXISTS pets_birthday_archived_lookup_index ON pets (birthday_month_day, is_archived, deleted_at)');
        }

        if (Schema::hasTable('breeds')) {
            DB::statement('CREATE INDEX IF NOT EXISTS breeds_species_normalized_name_cover_index ON breeds (species_id, normalized_name, name)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('pets')) {
            DB::statement('DROP INDEX IF EXISTS pets_birthday_archived_lookup_index');
        }

        if (Schema::hasTable('breeds')) {
            DB::statement('DROP INDEX IF EXISTS breeds_species_normalized_name_cover_index');
        }
    }
};
