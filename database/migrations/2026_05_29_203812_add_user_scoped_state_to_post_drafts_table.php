<?php

use App\Models\Content\PostDraft;
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
        if (! Schema::hasTable('post_drafts')) {
            return;
        }

        Schema::table('post_drafts', function (Blueprint $table) {
            if (! Schema::hasColumn('post_drafts', 'state')) {
                $table->json('state')->nullable();
            }
        });

        $this->backfillState();
        $this->deleteDuplicateUserDrafts();

        if ($this->hasIndexByName('post_drafts', 'post_drafts_user_context_unique')) {
            Schema::table('post_drafts', function (Blueprint $table): void {
                $table->dropUnique('post_drafts_user_context_unique');
            });
        }

        if (! $this->hasIndexByName('post_drafts', 'post_drafts_user_id_unique')) {
            Schema::table('post_drafts', function (Blueprint $table): void {
                $table->unique('user_id', 'post_drafts_user_id_unique');
            });
        }
    }

    private function backfillState(): void
    {
        PostDraft::query()
            ->whereNull('state')
            ->each(function (PostDraft $draft): void {
                $mediaPayload = is_array($draft->media_payload) ? $draft->media_payload : [];

                $draft->forceFill([
                    'state' => [
                        'text_content' => (string) ($draft->body ?? ''),
                        'temporary_file_paths' => collect($mediaPayload)
                            ->pluck('temporary_path')
                            ->filter()
                            ->values()
                            ->all(),
                        'attachment_metadata' => $mediaPayload,
                        'selected_pet_ids' => is_array($draft->tagged_pets) ? $draft->tagged_pets : [],
                        'location_display_text' => $draft->location,
                        'location_lat' => $draft->location_lat,
                        'location_lng' => $draft->location_lng,
                        'selected_mood' => $draft->mood,
                        'selected_visibility' => $draft->visibility,
                        'scheduled_publish_at' => $draft->scheduled_publish_at?->toIso8601String(),
                        'link_preview' => is_array($draft->link_preview) ? $draft->link_preview : [],
                        'context_type' => $draft->context_type,
                        'context_id' => $draft->context_id,
                    ],
                ])->save();
            });
    }

    private function deleteDuplicateUserDrafts(): void
    {
        $seenUserIds = [];
        $draftIdsToDelete = [];

        PostDraft::query()
            ->orderBy('user_id')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get(['id', 'user_id'])
            ->each(function (PostDraft $draft) use (&$seenUserIds, &$draftIdsToDelete): void {
                $userId = (int) $draft->user_id;

                if (isset($seenUserIds[$userId])) {
                    $draftIdsToDelete[] = (int) $draft->getKey();

                    return;
                }

                $seenUserIds[$userId] = true;
            });

        if ($draftIdsToDelete !== []) {
            PostDraft::query()
                ->whereIn('id', $draftIdsToDelete)
                ->delete();
        }
    }

    private function hasIndexByName(string $table, string $name): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn (array $index): bool => ($index['name'] ?? null) === $name);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('post_drafts')) {
            return;
        }

        if ($this->hasIndexByName('post_drafts', 'post_drafts_user_id_unique')) {
            Schema::table('post_drafts', function (Blueprint $table): void {
                $table->dropUnique('post_drafts_user_id_unique');
            });
        }

        if (! $this->hasIndexByName('post_drafts', 'post_drafts_user_context_unique')) {
            Schema::table('post_drafts', function (Blueprint $table): void {
                $table->unique(['user_id', 'context_type', 'context_id'], 'post_drafts_user_context_unique');
            });
        }

        Schema::table('post_drafts', function (Blueprint $table): void {
            if (Schema::hasColumn('post_drafts', 'state')) {
                $table->dropColumn('state');
            }
        });
    }
};
