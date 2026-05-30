<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            if (! Schema::hasColumn('posts', 'paw_count')) {
                $table->unsignedInteger('paw_count')->default(0)->after('reactions_count');
            }

            if (! Schema::hasColumn('posts', 'haha_count')) {
                $table->unsignedInteger('haha_count')->default(0)->after('love_count');
            }

            if (! Schema::hasColumn('posts', 'angry_count')) {
                $table->unsignedInteger('angry_count')->default(0)->after('sad_count');
            }
        });

        Schema::table('comments', function (Blueprint $table) {
            if (! Schema::hasColumn('comments', 'paw_count')) {
                $table->unsignedInteger('paw_count')->default(0)->after('reactions_count');
            }

            if (! Schema::hasColumn('comments', 'love_count')) {
                $table->unsignedInteger('love_count')->default(0)->after('paw_count');
            }
        });

        DB::table('reactions')
            ->whereIn('type', ['like', 'cute', 'support'])
            ->update(['type' => 'paw']);

        DB::table('reactions')
            ->whereIn('type', ['laugh', 'funny'])
            ->update(['type' => 'haha']);

        DB::table('reactions')
            ->where('type', 'care')
            ->update(['type' => 'love']);

        $this->syncCounterColumns();
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            foreach (['paw_count', 'haha_count', 'angry_count'] as $column) {
                if (Schema::hasColumn('posts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('comments', function (Blueprint $table) {
            foreach (['paw_count', 'love_count'] as $column) {
                if (Schema::hasColumn('comments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function syncCounterColumns(): void
    {
        foreach ([
            'paw_count' => 'paw',
            'love_count' => 'love',
            'haha_count' => 'haha',
            'wow_count' => 'wow',
            'sad_count' => 'sad',
            'angry_count' => 'angry',
        ] as $column => $type) {
            if (! Schema::hasColumn('posts', $column)) {
                continue;
            }

            DB::statement(
                sprintf(
                    "UPDATE posts SET %s = (SELECT COUNT(*) FROM reactions WHERE reactions.reactable_type = 'App\\Models\\Post' AND reactions.reactable_id = posts.id AND reactions.type = ?)",
                    $column,
                ),
                [$type],
            );
        }

        DB::statement("UPDATE posts SET reactions_count = (SELECT COUNT(*) FROM reactions WHERE reactions.reactable_type = 'App\\Models\\Post' AND reactions.reactable_id = posts.id)");
        DB::statement('UPDATE posts SET likes_count = reactions_count');

        foreach ([
            'paw_count' => 'paw',
            'love_count' => 'love',
        ] as $column => $type) {
            if (! Schema::hasColumn('comments', $column)) {
                continue;
            }

            DB::statement(
                sprintf(
                    "UPDATE comments SET %s = (SELECT COUNT(*) FROM reactions WHERE reactions.reactable_type = 'App\\Models\\Comment' AND reactions.reactable_id = comments.id AND reactions.type = ?)",
                    $column,
                ),
                [$type],
            );
        }

        DB::statement("UPDATE comments SET reactions_count = (SELECT COUNT(*) FROM reactions WHERE reactions.reactable_type = 'App\\Models\\Comment' AND reactions.reactable_id = comments.id)");
    }
};
