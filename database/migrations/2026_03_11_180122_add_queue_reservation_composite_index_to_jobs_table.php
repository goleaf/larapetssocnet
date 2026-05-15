<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const string LEGACY_QUEUE_INDEX = 'jobs_queue_index';

    private const string COMPOSITE_QUEUE_INDEX = 'jobs_queue_reserved_at_available_at_index';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('jobs')) {
            return;
        }

        $indexNames = collect(Schema::getIndexes('jobs'))
            ->pluck('name')
            ->all();

        Schema::table('jobs', function (Blueprint $table) use ($indexNames): void {
            if (in_array(self::LEGACY_QUEUE_INDEX, $indexNames, true)) {
                $table->dropIndex(self::LEGACY_QUEUE_INDEX);
            }

            if (! in_array(self::COMPOSITE_QUEUE_INDEX, $indexNames, true)) {
                $table->index(['queue', 'reserved_at', 'available_at'], self::COMPOSITE_QUEUE_INDEX);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('jobs')) {
            return;
        }

        $indexNames = collect(Schema::getIndexes('jobs'))
            ->pluck('name')
            ->all();

        Schema::table('jobs', function (Blueprint $table) use ($indexNames): void {
            if (in_array(self::COMPOSITE_QUEUE_INDEX, $indexNames, true)) {
                $table->dropIndex(self::COMPOSITE_QUEUE_INDEX);
            }

            if (! in_array(self::LEGACY_QUEUE_INDEX, $indexNames, true)) {
                $table->index('queue', self::LEGACY_QUEUE_INDEX);
            }
        });
    }
};
