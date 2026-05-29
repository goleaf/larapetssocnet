<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->expandSpeciesTable();
        $this->expandBreedsTable();
        $this->expandPetsTable();
        $this->expandPetOwnersTable();
        $this->expandPostsTable();
        $this->expandPetMilestonesTable();
        $this->createPetPostTable();
        $this->createPetWeightEntriesTable();
        $this->createPetOwnerInvitationsTable();
        $this->createPetOwnershipTransfersTable();
        $this->createPetRelationshipsTable();
        $this->createPetHealthRemindersTable();
        $this->createPetAdoptionInquiriesTable();
        $this->createPetSpotlightFeaturesTable();
        $this->backfillSpeciesAndBreeds();
        $this->backfillPetOwnerRoles();
        $this->backfillPetPostTags();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pet_spotlight_features');
        Schema::dropIfExists('pet_adoption_inquiries');
        Schema::dropIfExists('pet_health_reminders');
        Schema::dropIfExists('pet_relationships');
        Schema::dropIfExists('pet_ownership_transfers');
        Schema::dropIfExists('pet_owner_invitations');
        Schema::dropIfExists('pet_weight_entries');
        Schema::dropIfExists('pet_post');

        $this->dropColumns('pet_milestones', [
            'photo_path',
            'location_name',
            'location_lat',
            'location_lng',
        ]);

        $this->dropColumns('posts', [
            'is_system_generated',
            'system_source',
            'location_lat',
            'location_lng',
        ]);

        $this->dropColumns('pet_owners', [
            'is_primary_owner',
        ]);

        $this->dropColumns('pets', [
            'species_id',
            'breed_id',
            'breed_description',
            'birth_year',
            'photo_count',
            'is_archived',
            'weight_unit',
            'microchip_number',
            'cover_photo_path',
            'adoption_story',
            'adoption_requirements',
            'adoption_location',
            'adoption_contact_preference',
        ]);

        $this->dropColumns('breeds', [
            'species_id',
            'normalized_name',
        ]);

        $this->dropColumns('species', [
            'icon_identifier',
            'color_identifier',
            'gradient_from',
            'gradient_to',
            'display_order',
            'life_stage_config',
        ]);
    }

    private function expandSpeciesTable(): void
    {
        if (! Schema::hasTable('species')) {
            return;
        }

        Schema::table('species', function (Blueprint $table): void {
            if (! Schema::hasColumn('species', 'icon_identifier')) {
                $table->string('icon_identifier', 80)->nullable()->after('slug');
            }

            if (! Schema::hasColumn('species', 'color_identifier')) {
                $table->string('color_identifier', 80)->nullable()->after('icon_identifier');
            }

            if (! Schema::hasColumn('species', 'gradient_from')) {
                $table->string('gradient_from', 40)->nullable()->after('color_identifier');
            }

            if (! Schema::hasColumn('species', 'gradient_to')) {
                $table->string('gradient_to', 40)->nullable()->after('gradient_from');
            }

            if (! Schema::hasColumn('species', 'display_order')) {
                $table->unsignedSmallInteger('display_order')->default(0)->after('gradient_to');
            }

            if (! Schema::hasColumn('species', 'life_stage_config')) {
                $table->text('life_stage_config')->nullable()->after('display_order');
            }
        });

        $this->statement('CREATE INDEX IF NOT EXISTS species_display_order_index ON species (display_order, name)');
    }

    private function expandBreedsTable(): void
    {
        if (! Schema::hasTable('breeds')) {
            return;
        }

        Schema::table('breeds', function (Blueprint $table): void {
            if (! Schema::hasColumn('breeds', 'species_id')) {
                $table->unsignedBigInteger('species_id')->nullable()->after('species_slug');
            }

            if (! Schema::hasColumn('breeds', 'normalized_name')) {
                $table->string('normalized_name')->nullable()->after('name');
            }
        });

        $this->statement('CREATE INDEX IF NOT EXISTS breeds_species_id_normalized_name_index ON breeds (species_id, normalized_name)');
    }

    private function expandPetsTable(): void
    {
        if (! Schema::hasTable('pets')) {
            return;
        }

        Schema::table('pets', function (Blueprint $table): void {
            if (! Schema::hasColumn('pets', 'species_id')) {
                $table->unsignedBigInteger('species_id')->nullable()->after('species');
            }

            if (! Schema::hasColumn('pets', 'breed_id')) {
                $table->unsignedBigInteger('breed_id')->nullable()->after('breed');
            }

            if (! Schema::hasColumn('pets', 'breed_description')) {
                $table->string('breed_description', 120)->nullable()->after('breed_id');
            }

            if (! Schema::hasColumn('pets', 'birth_year')) {
                $table->unsignedSmallInteger('birth_year')->nullable()->after('date_of_birth');
            }

            if (! Schema::hasColumn('pets', 'photo_count')) {
                $table->unsignedInteger('photo_count')->default(0)->after('posts_count');
            }

            if (! Schema::hasColumn('pets', 'is_archived')) {
                $table->boolean('is_archived')->default(false)->after('is_deceased');
            }

            if (! Schema::hasColumn('pets', 'weight_unit')) {
                $table->string('weight_unit', 10)->default('kg')->after('weight_kg');
            }

            if (! Schema::hasColumn('pets', 'microchip_number')) {
                $table->string('microchip_number', 80)->nullable()->after('microchipped_status');
            }

            if (! Schema::hasColumn('pets', 'cover_photo_path')) {
                $table->string('cover_photo_path')->nullable()->after('avatar_path');
            }

            if (! Schema::hasColumn('pets', 'adoption_story')) {
                $table->text('adoption_story')->nullable()->after('adoption_notes');
            }

            if (! Schema::hasColumn('pets', 'adoption_requirements')) {
                $table->text('adoption_requirements')->nullable()->after('adoption_story');
            }

            if (! Schema::hasColumn('pets', 'adoption_location')) {
                $table->string('adoption_location')->nullable()->after('adoption_requirements');
            }

            if (! Schema::hasColumn('pets', 'adoption_contact_preference')) {
                $table->string('adoption_contact_preference', 40)->default('public_form')->after('adoption_contact');
            }
        });

        $this->statement('CREATE INDEX IF NOT EXISTS pets_species_id_created_at_index ON pets (species_id, created_at)');
        $this->statement('CREATE INDEX IF NOT EXISTS pets_breed_id_index ON pets (breed_id)');
        $this->statement('CREATE INDEX IF NOT EXISTS pets_archived_visibility_index ON pets (is_archived, visibility, deleted_at)');
    }

    private function expandPetOwnersTable(): void
    {
        if (! Schema::hasTable('pet_owners')) {
            return;
        }

        Schema::table('pet_owners', function (Blueprint $table): void {
            if (! Schema::hasColumn('pet_owners', 'is_primary_owner')) {
                $table->boolean('is_primary_owner')->default(false)->after('role');
            }
        });

        $this->statement('CREATE INDEX IF NOT EXISTS pet_owners_pet_role_index ON pet_owners (pet_id, role)');
        $this->statement('CREATE INDEX IF NOT EXISTS pet_owners_pet_primary_index ON pet_owners (pet_id, is_primary_owner)');
    }

    private function expandPostsTable(): void
    {
        if (! Schema::hasTable('posts')) {
            return;
        }

        Schema::table('posts', function (Blueprint $table): void {
            if (! Schema::hasColumn('posts', 'is_system_generated')) {
                $table->boolean('is_system_generated')->default(false)->after('is_pinned');
            }

            if (! Schema::hasColumn('posts', 'system_source')) {
                $table->string('system_source', 80)->nullable()->after('is_system_generated');
            }

            if (! Schema::hasColumn('posts', 'location_lat')) {
                $table->decimal('location_lat', 10, 7)->nullable()->after('location');
            }

            if (! Schema::hasColumn('posts', 'location_lng')) {
                $table->decimal('location_lng', 10, 7)->nullable()->after('location_lat');
            }
        });

        $this->statement('CREATE INDEX IF NOT EXISTS posts_system_source_index ON posts (is_system_generated, system_source)');
        $this->statement('CREATE INDEX IF NOT EXISTS posts_location_coordinates_index ON posts (location_lat, location_lng)');
    }

    private function expandPetMilestonesTable(): void
    {
        if (! Schema::hasTable('pet_milestones')) {
            return;
        }

        Schema::table('pet_milestones', function (Blueprint $table): void {
            if (! Schema::hasColumn('pet_milestones', 'photo_path')) {
                $table->string('photo_path')->nullable()->after('body_html');
            }

            if (! Schema::hasColumn('pet_milestones', 'location_name')) {
                $table->string('location_name')->nullable()->after('occurred_on');
            }

            if (! Schema::hasColumn('pet_milestones', 'location_lat')) {
                $table->decimal('location_lat', 10, 7)->nullable()->after('location_name');
            }

            if (! Schema::hasColumn('pet_milestones', 'location_lng')) {
                $table->decimal('location_lng', 10, 7)->nullable()->after('location_lat');
            }
        });

        $this->statement('CREATE INDEX IF NOT EXISTS pet_milestones_pet_location_index ON pet_milestones (pet_id, location_name)');
    }

    private function createPetPostTable(): void
    {
        if (Schema::hasTable('pet_post')) {
            return;
        }

        Schema::create('pet_post', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pet_id')->constrained('pets')->cascadeOnDelete();
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['pet_id', 'post_id']);
            $table->index(['post_id', 'pet_id']);
            $table->index(['pet_id', 'is_primary']);
        });
    }

    private function createPetWeightEntriesTable(): void
    {
        if (Schema::hasTable('pet_weight_entries')) {
            return;
        }

        Schema::create('pet_weight_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pet_id')->constrained('pets')->cascadeOnDelete();
            $table->date('entry_date');
            $table->decimal('weight_value', 8, 2);
            $table->string('weight_unit', 10)->default('kg');
            $table->string('note', 100)->nullable();
            $table->timestamps();

            $table->unique(['pet_id', 'entry_date']);
            $table->index(['pet_id', 'entry_date']);
        });
    }

    private function createPetOwnerInvitationsTable(): void
    {
        if (Schema::hasTable('pet_owner_invitations')) {
            return;
        }

        Schema::create('pet_owner_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pet_id')->constrained('pets')->cascadeOnDelete();
            $table->foreignId('invited_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('inviting_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 20);
            $table->string('status', 20)->default('pending');
            $table->timestamp('expires_at');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['pet_id', 'invited_user_id', 'status']);
            $table->index(['invited_user_id', 'status', 'expires_at']);
            $table->index(['pet_id', 'status']);
        });
    }

    private function createPetOwnershipTransfersTable(): void
    {
        if (Schema::hasTable('pet_ownership_transfers')) {
            return;
        }

        Schema::create('pet_ownership_transfers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pet_id')->constrained('pets')->cascadeOnDelete();
            $table->foreignId('current_owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('proposed_owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('pending');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['pet_id', 'status', 'expires_at']);
            $table->index(['proposed_owner_user_id', 'status']);
        });
    }

    private function createPetRelationshipsTable(): void
    {
        if (Schema::hasTable('pet_relationships')) {
            return;
        }

        Schema::create('pet_relationships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_pet_id')->constrained('pets')->cascadeOnDelete();
            $table->foreignId('target_pet_id')->constrained('pets')->cascadeOnDelete();
            $table->string('relationship_type', 20);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['source_pet_id', 'target_pet_id']);
            $table->index(['target_pet_id', 'relationship_type']);
        });
    }

    private function createPetHealthRemindersTable(): void
    {
        if (Schema::hasTable('pet_health_reminders')) {
            return;
        }

        Schema::create('pet_health_reminders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pet_id')->constrained('pets')->cascadeOnDelete();
            $table->string('reminder_type', 40);
            $table->string('custom_text')->nullable();
            $table->unsignedSmallInteger('frequency_days');
            $table->date('last_sent_on')->nullable();
            $table->date('next_due_on');
            $table->timestamps();

            $table->index(['next_due_on', 'pet_id']);
            $table->index(['pet_id', 'reminder_type']);
        });
    }

    private function createPetAdoptionInquiriesTable(): void
    {
        if (Schema::hasTable('pet_adoption_inquiries')) {
            return;
        }

        Schema::create('pet_adoption_inquiries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pet_id')->constrained('pets')->cascadeOnDelete();
            $table->foreignId('marketplace_listing_id')->nullable()->constrained('marketplace_listings')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('applicant_name');
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->string('living_situation', 40);
            $table->string('species_experience', 40);
            $table->string('other_pets')->nullable();
            $table->text('message');
            $table->string('preferred_contact_method', 40);
            $table->string('contact_details');
            $table->string('status', 20)->default('sent');
            $table->timestamps();

            $table->index(['pet_id', 'created_at']);
            $table->index(['user_id', 'pet_id', 'created_at']);
        });
    }

    private function createPetSpotlightFeaturesTable(): void
    {
        if (Schema::hasTable('pet_spotlight_features')) {
            return;
        }

        Schema::create('pet_spotlight_features', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pet_id')->constrained('pets')->cascadeOnDelete();
            $table->date('featured_week_start');
            $table->decimal('engagement_rate', 8, 4)->default(0);
            $table->timestamp('selected_at');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique(['pet_id', 'featured_week_start']);
            $table->index(['featured_week_start', 'expires_at']);
        });
    }

    private function backfillSpeciesAndBreeds(): void
    {
        if (Schema::hasTable('breeds') && Schema::hasColumn('breeds', 'normalized_name')) {
            DB::table('breeds')
                ->whereNull('normalized_name')
                ->orWhere('normalized_name', '')
                ->orderBy('id')
                ->lazyById(500)
                ->each(function (object $breed): void {
                    DB::table('breeds')->where('id', $breed->id)->update([
                        'normalized_name' => $this->normalizeSearchName((string) $breed->name),
                    ]);
                });
        }

        if (Schema::hasTable('breeds') && Schema::hasColumn('breeds', 'species_id')) {
            DB::table('breeds')
                ->select(['id', 'species_slug'])
                ->whereNull('breeds.species_id')
                ->orderBy('id')
                ->lazyById(500)
                ->each(function (object $breed): void {
                    $speciesId = DB::table('species')
                        ->where('slug', (string) $breed->species_slug)
                        ->value('id');

                    if (! $speciesId) {
                        return;
                    }

                    DB::table('breeds')->where('id', $breed->id)->update([
                        'species_id' => $speciesId,
                    ]);
                });
        }

        if (! Schema::hasTable('pets') || ! Schema::hasColumn('pets', 'species_id')) {
            return;
        }

        DB::table('pets')
            ->select(['id', 'species', 'breed'])
            ->orderBy('id')
            ->lazyById(500)
            ->each(function (object $pet): void {
                $speciesId = DB::table('species')
                    ->where('slug', (string) $pet->species)
                    ->value('id');

                $breedId = null;
                if ($speciesId && is_string($pet->breed) && trim($pet->breed) !== '') {
                    $breedId = DB::table('breeds')
                        ->where('species_id', $speciesId)
                        ->where('normalized_name', $this->normalizeSearchName($pet->breed))
                        ->value('id');
                }

                DB::table('pets')->where('id', $pet->id)->update([
                    'species_id' => $speciesId,
                    'breed_id' => $breedId,
                    'breed_description' => $breedId ? null : $pet->breed,
                ]);
            });
    }

    private function backfillPetOwnerRoles(): void
    {
        if (! Schema::hasTable('pet_owners') || ! Schema::hasColumn('pet_owners', 'is_primary_owner')) {
            return;
        }

        DB::table('pet_owners')
            ->join('pets', 'pets.id', '=', 'pet_owners.pet_id')
            ->whereColumn('pet_owners.user_id', 'pets.user_id')
            ->update([
                'role' => 'owner',
                'is_primary_owner' => true,
                'accepted_at' => DB::raw('COALESCE(pet_owners.accepted_at, pet_owners.created_at)'),
            ]);

        DB::table('pet_owners')
            ->where('role', 'co_owner')
            ->where(function ($query): void {
                $query
                    ->where('can_edit', true)
                    ->orWhere('can_manage_health', true)
                    ->orWhere('can_manage_gallery', true)
                    ->orWhere('can_manage_adoption', true)
                    ->orWhere('can_delete', true);
            })
            ->update(['role' => 'admin']);

        DB::table('pet_owners')
            ->where('role', 'co_owner')
            ->where('can_post', true)
            ->update(['role' => 'poster']);

        DB::table('pet_owners')
            ->where('role', 'co_owner')
            ->update(['role' => 'viewer']);
    }

    private function backfillPetPostTags(): void
    {
        if (! Schema::hasTable('pet_post') || ! Schema::hasTable('posts')) {
            return;
        }

        DB::table('posts')
            ->select(['id', 'pet_id', 'tagged_pets'])
            ->orderBy('id')
            ->lazyById(500)
            ->each(function (object $post): void {
                $petIds = collect([(int) ($post->pet_id ?? 0)])
                    ->merge($this->decodeTaggedPets($post->tagged_pets ?? null))
                    ->filter(fn (int $petId): bool => $petId > 0)
                    ->unique()
                    ->values();

                foreach ($petIds as $petId) {
                    DB::table('pet_post')->updateOrInsert([
                        'pet_id' => $petId,
                        'post_id' => $post->id,
                    ], [
                        'is_primary' => (int) ($post->pet_id ?? 0) === $petId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    /**
     * @return list<int>
     */
    private function decodeTaggedPets(mixed $value): array
    {
        if (is_array($value)) {
            return array_map('intval', $value);
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        if (! is_array($decoded)) {
            return [];
        }

        return collect($decoded)
            ->map(fn (mixed $petId): int => (int) $petId)
            ->all();
    }

    private function normalizeSearchName(string $value): string
    {
        return Str::of($value)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/u', '')
            ->toString();
    }

    /**
     * @param  list<string>  $columns
     */
    private function dropColumns(string $table, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table, $columns): void {
            foreach ($columns as $column) {
                if (Schema::hasColumn($table, $column)) {
                    $blueprint->dropColumn($column);
                }
            }
        });
    }

    private function statement(string $sql): void
    {
        try {
            DB::statement($sql);
        } catch (Throwable) {
            //
        }
    }
};
