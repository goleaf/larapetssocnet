<?php

use App\Models\Content\Comment;
use App\Services\ContentService;
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
        Schema::table('comments', function (Blueprint $table): void {
            $table->text('body_html')->nullable()->after('body');
        });

        if (! Schema::hasColumn('comments', 'body_html')) {
            return;
        }

        $content = app(ContentService::class);

        Comment::query()
            ->whereNull('body_html')
            ->chunkById(200, function ($comments) use ($content): void {
                foreach ($comments as $comment) {
                    $comment->updateQuietly([
                        'body_html' => $content->process((string) $comment->body),
                    ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table): void {
            $table->dropColumn('body_html');
        });
    }
};
