<?php

use App\Models\Groups\Group;
use App\Services\ContentService;
use App\Services\GroupSlugService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('groups')) {
            return;
        }

        Schema::table('groups', function (Blueprint $table): void {
            if (! Schema::hasColumn('groups', 'description_html')) {
                $table->text('description_html')->nullable()->after('description');
            }

            if (! Schema::hasColumn('groups', 'rules')) {
                $table->text('rules')->nullable()->after('description_html');
            }
        });

        $this->backfillGroupContent();
    }

    public function down(): void
    {
        if (! Schema::hasTable('groups')) {
            return;
        }

        Schema::table('groups', function (Blueprint $table): void {
            if (Schema::hasColumn('groups', 'rules')) {
                $table->dropColumn('rules');
            }

            if (Schema::hasColumn('groups', 'description_html')) {
                $table->dropColumn('description_html');
            }
        });
    }

    private function backfillGroupContent(): void
    {
        if (! Schema::hasTable('groups')) {
            return;
        }

        $slugService = app(GroupSlugService::class);
        $content = app(ContentService::class);

        Group::query()
            ->select(['id', 'name', 'slug', 'description', 'description_html'])
            ->orderBy('id')
            ->chunkById(100, function ($groups) use ($slugService, $content): void {
                foreach ($groups as $group) {
                    $updates = [];

                    if (blank($group->slug)) {
                        $seed = (string) ($group->name ?: 'group');
                        $updates['slug'] = $slugService->generateUnique($seed, (int) $group->getKey());
                    }

                    if (blank($group->description_html) && filled((string) $group->description)) {
                        $updates['description_html'] = $content->process((string) $group->description);
                    }

                    if ($updates !== []) {
                        $group->updateQuietly($updates);
                    }
                }
            });
    }
};
