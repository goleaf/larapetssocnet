<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Content\Comment;
use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Support\Seeding\SeedProfile;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    private const COMMENT_TABLE = 'comments';

    private const LIKE_TABLE = 'likes';

    private const REACTION_TABLE = 'reactions';

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $profile = SeedProfile::fromConfig();
        $confirmed = (bool) config('seeding.performance_confirmation', false);

        if ($profile instanceof SeedProfile) {
            if (! $profile->isAllowedInCurrentEnvironment(app(), $confirmed)) {
                throw new RuntimeException('The selected seeding profile is not allowed in this environment. Use --confirm-performance in a non-production safe environment.');
            }

            config(['seeding.current_profile' => $profile->value]);
        } else {
            config(['seeding.current_profile' => null]);
        }

        $seeders = [
            // Foundation: users, roles, reserved usernames, badges
            RoleSeeder::class,
            ReservedUsernameSeeder::class,
            UserSeeder::class,
            BadgeSeeder::class,

            // Pets (depends on users)
            SpeciesSeeder::class,
            BreedSeeder::class,
            PetSeeder::class,
            AdoptablePetSeeder::class,

            // Social graph: follows, blocks, pet follows (depends on users + pets)
            FollowSeeder::class,
            SocialSeeder::class,

            // Content: posts, comments, likes, hashtags (depends on users + pets)
            PostSeeder::class,
        ];

        if ($profile === null || $profile->contentPosts() > 0) {
            $seeders[] = ContentSeeder::class;
        }

        $seeders = array_merge($seeders, [
            // Groups + members + posts + bans + join requests (depends on users + posts)
            GroupSeeder::class,
            GroupBanSeeder::class,
            GroupJoinRequestSeeder::class,

            // Events + attendees (depends on groups + users)
            EventSeeder::class,

            // Marketplace listings (depends on users + pets)
            ListingSeeder::class,
            MarketplaceSeeder::class,

            // Conversations + messages (depends on users)
            ConversationSeeder::class,
            UserBlockSeeder::class,

            // Contests + entries + votes (depends on users + pets)
            ContestSeeder::class,

            // Pet health logs (depends on pets + users)
            HealthSeeder::class,

            // Misc: blocks (standalone, depends on users)
            BlockSeeder::class,
        ]);

        $this->call($seeders);

        $this->outputSummary($profile);
    }

    private function outputSummary(?SeedProfile $profile): void
    {
        if (! $this->command) {
            return;
        }

        if ($profile === null) {
            $this->command->info('Database seeded in legacy mode.');

            return;
        }

        $summary = $profile->seedCountSummary();

        $this->command->info(sprintf(
            'Seed profile `%s` loaded: users=%d pets=%d posts=%d comments=%d likes=%d.',
            $profile->value,
            $summary['users'],
            $summary['pets'],
            $summary['posts'],
            $summary['comments'],
            $summary['likes'],
        ));

        $this->command->line(sprintf(
            'Actual rows: users=%d pets=%d posts=%d comments=%d likes=%d reactions=%d.',
            User::query()->count(),
            Pet::query()->count(),
            Post::query()->count(),
            Comment::query()->count(),
            Schema::hasTable('likes') ? DB::table(self::LIKE_TABLE)->count() : 0,
            Schema::hasTable('reactions') ? DB::table(self::REACTION_TABLE)->count() : 0,
        ));
    }
}
