<?php

use App\Models\Content\Hashtag;
use App\Support\Hashtags\HashtagNormalizer;
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
        if (! Schema::hasColumn('hashtags', 'normalized_name')) {
            Schema::table('hashtags', function (Blueprint $table): void {
                $table->string('normalized_name', 50)->nullable()->unique();
            });
        }

        $normalizer = new HashtagNormalizer;

        Hashtag::query()
            ->whereNull('normalized_name')
            ->chunkById(200, function ($hashtags) use ($normalizer): void {
                foreach ($hashtags as $hashtag) {
                    $normalized = $normalizer->normalize((string) $hashtag->name);

                    if (! $normalized) {
                        continue;
                    }

                    $hashtag->updateQuietly([
                        'normalized_name' => $normalized,
                        'slug' => $normalized,
                    ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('hashtags', 'normalized_name')) {
            return;
        }

        Schema::table('hashtags', function (Blueprint $table): void {
            $table->dropUnique(['normalized_name']);
            $table->dropColumn('normalized_name');
        });
    }
};
