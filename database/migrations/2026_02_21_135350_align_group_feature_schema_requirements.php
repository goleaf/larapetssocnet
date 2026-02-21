<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->alignGroupsTable();
        $this->alignGroupMembersTable();
        $this->createGroupJoinRequestsTable();
        $this->createGroupBansTable();
        $this->alignPostsTable();
    }

    public function down(): void
    {
        Schema::dropIfExists('group_bans');
        Schema::dropIfExists('group_join_requests');
    }

    private function alignGroupsTable(): void
    {
        if (! Schema::hasTable('groups')) {
            return;
        }

        Schema::table('groups', function (Blueprint $table): void {
            if (! Schema::hasColumn('groups', 'owner_id')) {
                $table->foreignId('owner_id')->nullable()->constrained('users');
            }

            if (! Schema::hasColumn('groups', 'avatar')) {
                $table->string('avatar')->nullable();
            }

            if (! Schema::hasColumn('groups', 'cover_image')) {
                $table->string('cover_image')->nullable();
            }

            if (! Schema::hasColumn('groups', 'type')) {
                $table->enum('type', ['public', 'private', 'secret'])->default('public');
            }

            if (! Schema::hasColumn('groups', 'species_focus')) {
                $table->enum('species_focus', ['dog', 'cat', 'bird', 'rabbit', 'fish', 'reptile', 'other', 'all'])->default('all');
            }

            if (! Schema::hasColumn('groups', 'members_count')) {
                $table->unsignedInteger('members_count')->default(0);
            }

            if (! Schema::hasColumn('groups', 'posts_count')) {
                $table->unsignedInteger('posts_count')->default(0);
            }
        });

        if (Schema::hasColumn('groups', 'owner_user_id') && Schema::hasColumn('groups', 'owner_id')) {
            DB::table('groups')
                ->whereNull('owner_id')
                ->whereNotNull('owner_user_id')
                ->update(['owner_id' => DB::raw('owner_user_id')]);
        }

        if (Schema::hasColumn('groups', 'cover_image_path') && Schema::hasColumn('groups', 'cover_image')) {
            DB::table('groups')
                ->whereNull('cover_image')
                ->whereNotNull('cover_image_path')
                ->update(['cover_image' => DB::raw('cover_image_path')]);
        }

        if (Schema::hasColumn('groups', 'privacy') && Schema::hasColumn('groups', 'type')) {
            DB::table('groups')
                ->whereNull('type')
                ->whereIn('privacy', ['public', 'private', 'secret'])
                ->update(['type' => DB::raw('privacy')]);
        }

        if (Schema::hasColumn('groups', 'type')) {
            DB::table('groups')
                ->where(function ($query): void {
                    $query->whereNull('type')
                        ->orWhereNotIn('type', ['public', 'private', 'secret']);
                })
                ->update(['type' => 'public']);
        }

        if (Schema::hasColumn('groups', 'species_focus')) {
            DB::table('groups')
                ->where(function ($query): void {
                    $query->whereNull('species_focus')
                        ->orWhereNotIn('species_focus', ['dog', 'cat', 'bird', 'rabbit', 'fish', 'reptile', 'other', 'all']);
                })
                ->update(['species_focus' => 'all']);
        }

        if (Schema::hasColumn('groups', 'members_count')) {
            DB::table('groups')
                ->whereNull('members_count')
                ->update(['members_count' => 0]);
        }

        if (Schema::hasColumn('groups', 'posts_count')) {
            DB::table('groups')
                ->whereNull('posts_count')
                ->update(['posts_count' => 0]);
        }

        if (Schema::hasColumn('groups', 'name')) {
            DB::table('groups')
                ->select(['id', 'name'])
                ->orderBy('id')
                ->get()
                ->each(function (object $group): void {
                    if (mb_strlen($group->name) > 100) {
                        DB::table('groups')
                            ->where('id', $group->id)
                            ->update(['name' => mb_substr($group->name, 0, 100)]);
                    }
                });
        }

        if (Schema::hasColumn('groups', 'owner_id')) {
            $fallbackOwnerId = DB::table('users')->min('id');

            if ($fallbackOwnerId !== null) {
                DB::table('groups')
                    ->whereNull('owner_id')
                    ->update(['owner_id' => $fallbackOwnerId]);
            }
        }

        if (Schema::hasColumn('groups', 'owner_id') && ! $this->hasForeignKey('groups', 'owner_id')) {
            Schema::table('groups', function (Blueprint $table): void {
                $table->foreign('owner_id')->references('id')->on('users');
            });
        }

        Schema::table('groups', function (Blueprint $table): void {
            if (Schema::hasColumn('groups', 'owner_id')) {
                $table->unsignedBigInteger('owner_id')->nullable(false)->change();
            }

            if (Schema::hasColumn('groups', 'name')) {
                $table->string('name', 100)->change();
            }

            if (Schema::hasColumn('groups', 'type')) {
                $table->enum('type', ['public', 'private', 'secret'])->default('public')->change();
            }

            if (Schema::hasColumn('groups', 'species_focus')) {
                $table->enum('species_focus', ['dog', 'cat', 'bird', 'rabbit', 'fish', 'reptile', 'other', 'all'])->default('all')->change();
            }

            if (Schema::hasColumn('groups', 'members_count')) {
                $table->unsignedInteger('members_count')->default(0)->change();
            }

            if (Schema::hasColumn('groups', 'posts_count')) {
                $table->unsignedInteger('posts_count')->default(0)->change();
            }
        });

        if (Schema::hasColumn('groups', 'slug') && ! Schema::hasIndex('groups', ['slug'], 'unique')) {
            Schema::table('groups', function (Blueprint $table): void {
                $table->unique('slug');
            });
        }
    }

    private function alignGroupMembersTable(): void
    {
        if (! Schema::hasTable('group_members')) {
            return;
        }

        Schema::table('group_members', function (Blueprint $table): void {
            if (! Schema::hasColumn('group_members', 'role')) {
                $table->enum('role', ['owner', 'admin', 'moderator', 'member'])->default('member');
            }

            if (! Schema::hasColumn('group_members', 'joined_at')) {
                $table->timestamp('joined_at')->nullable();
            }
        });

        if (Schema::hasColumn('group_members', 'role')) {
            DB::table('group_members')
                ->where(function ($query): void {
                    $query->whereNull('role')
                        ->orWhereNotIn('role', ['owner', 'admin', 'moderator', 'member']);
                })
                ->update(['role' => 'member']);
        }

        Schema::table('group_members', function (Blueprint $table): void {
            if (Schema::hasColumn('group_members', 'role')) {
                $table->enum('role', ['owner', 'admin', 'moderator', 'member'])->default('member')->change();
            }
        });

        if (! Schema::hasIndex('group_members', ['group_id', 'user_id'], 'unique')) {
            Schema::table('group_members', function (Blueprint $table): void {
                $table->unique(['group_id', 'user_id']);
            });
        }

        if (Schema::hasColumn('group_members', 'group_id') && ! $this->hasForeignKey('group_members', 'group_id')) {
            Schema::table('group_members', function (Blueprint $table): void {
                $table->foreign('group_id')->references('id')->on('groups')->cascadeOnDelete();
            });
        }

        if (Schema::hasColumn('group_members', 'user_id') && ! $this->hasForeignKey('group_members', 'user_id')) {
            Schema::table('group_members', function (Blueprint $table): void {
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }
    }

    private function createGroupJoinRequestsTable(): void
    {
        if (Schema::hasTable('group_join_requests')) {
            return;
        }

        Schema::create('group_join_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('message')->nullable();
            $table->timestamps();

            $table->unique(['group_id', 'user_id']);
        });
    }

    private function createGroupBansTable(): void
    {
        if (Schema::hasTable('group_bans')) {
            return;
        }

        Schema::create('group_bans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('banned_by')->constrained('users');
            $table->string('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['group_id', 'user_id']);
        });
    }

    private function alignPostsTable(): void
    {
        if (! Schema::hasTable('posts')) {
            return;
        }

        Schema::table('posts', function (Blueprint $table): void {
            if (! Schema::hasColumn('posts', 'group_id')) {
                $table->foreignId('group_id')->nullable()->constrained('groups')->nullOnDelete();
            }
        });

        if (Schema::hasColumn('posts', 'group_id') && ! $this->hasForeignKey('posts', 'group_id')) {
            Schema::table('posts', function (Blueprint $table): void {
                $table->foreign('group_id')->references('id')->on('groups')->nullOnDelete();
            });
        }

        if (Schema::hasColumn('posts', 'group_id') && ! Schema::hasIndex('posts', ['group_id'])) {
            Schema::table('posts', function (Blueprint $table): void {
                $table->index('group_id');
            });
        }
    }

    private function hasForeignKey(string $table, string $column): bool
    {
        if (! Schema::hasTable($table)) {
            return false;
        }

        foreach (Schema::getForeignKeys($table) as $foreignKey) {
            if (in_array($column, $foreignKey['columns'] ?? [], true)) {
                return true;
            }
        }

        return false;
    }
};
