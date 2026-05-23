<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('group_invitations')) {
            Schema::create('group_invitations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
                $table->foreignId('invited_user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('invited_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status', 24)->default('pending');
                $table->string('role', 24)->default('member');
                $table->string('message', 500)->nullable();
                $table->timestamp('responded_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();

                $table->unique(['group_id', 'invited_user_id'], 'group_invitations_group_user_unique');
                $table->index(['group_id', 'status'], 'group_invitations_group_status_index');
                $table->index(['invited_user_id', 'status'], 'group_invitations_user_status_index');
                $table->index(['status', 'expires_at'], 'group_invitations_status_expires_index');
            });
        }

        if (Schema::hasTable('group_members') && ! $this->hasIndexByName('group_members', 'group_members_group_status_role_joined_index')) {
            Schema::table('group_members', function (Blueprint $table): void {
                $table->index(['group_id', 'status', 'role', 'joined_at'], 'group_members_group_status_role_joined_index');
            });
        }

        if (Schema::hasTable('group_members') && ! $this->hasIndexByName('group_members', 'group_members_group_status_joined_index')) {
            Schema::table('group_members', function (Blueprint $table): void {
                $table->index(['group_id', 'status', 'joined_at'], 'group_members_group_status_joined_index');
            });
        }

        if (Schema::hasTable('posts') && Schema::hasColumn('posts', 'group_id') && ! $this->hasIndexByName('posts', 'posts_group_feed_lookup_index')) {
            Schema::table('posts', function (Blueprint $table): void {
                $table->index(['group_id', 'status', 'published_at', 'created_at', 'id'], 'posts_group_feed_lookup_index');
            });
        }

        if (Schema::hasTable('groups') && ! $this->hasIndexByName('groups', 'groups_privacy_name_index')) {
            Schema::table('groups', function (Blueprint $table): void {
                $table->index(['privacy', 'name'], 'groups_privacy_name_index');
            });
        }

        if (Schema::hasTable('groups') && ! $this->hasIndexByName('groups', 'groups_type_name_index')) {
            Schema::table('groups', function (Blueprint $table): void {
                $table->index(['type', 'name'], 'groups_type_name_index');
            });
        }

        if (Schema::hasTable('groups') && ! $this->hasIndexByName('groups', 'groups_name_lookup_index')) {
            Schema::table('groups', function (Blueprint $table): void {
                $table->index('name', 'groups_name_lookup_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('groups')) {
            Schema::table('groups', function (Blueprint $table): void {
                if ($this->hasIndexByName('groups', 'groups_name_lookup_index')) {
                    $table->dropIndex('groups_name_lookup_index');
                }

                if ($this->hasIndexByName('groups', 'groups_type_name_index')) {
                    $table->dropIndex('groups_type_name_index');
                }

                if ($this->hasIndexByName('groups', 'groups_privacy_name_index')) {
                    $table->dropIndex('groups_privacy_name_index');
                }
            });
        }

        if (Schema::hasTable('posts') && $this->hasIndexByName('posts', 'posts_group_feed_lookup_index')) {
            Schema::table('posts', function (Blueprint $table): void {
                $table->dropIndex('posts_group_feed_lookup_index');
            });
        }

        if (Schema::hasTable('group_members')) {
            Schema::table('group_members', function (Blueprint $table): void {
                if ($this->hasIndexByName('group_members', 'group_members_group_status_joined_index')) {
                    $table->dropIndex('group_members_group_status_joined_index');
                }

                if ($this->hasIndexByName('group_members', 'group_members_group_status_role_joined_index')) {
                    $table->dropIndex('group_members_group_status_role_joined_index');
                }
            });
        }

        Schema::dropIfExists('group_invitations');
    }

    private function hasIndexByName(string $table, string $name): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn (array $index): bool => ($index['name'] ?? null) === $name);
    }
};
