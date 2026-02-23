<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            // Foundation: users, roles, reserved usernames, badges
            RoleSeeder::class,
            ReservedUsernameSeeder::class,
            UserSeeder::class,
            BadgeSeeder::class,

            // Pets (depends on users)
            PetSeeder::class,
            AdoptablePetSeeder::class,

            // Social graph: follows, blocks, pet follows (depends on users + pets)
            FollowSeeder::class,
            SocialSeeder::class,

            // Content: posts, comments, likes, hashtags (depends on users + pets)
            PostSeeder::class,
            ContentSeeder::class,

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

            // Contests + entries + votes (depends on users + pets)
            ContestSeeder::class,

            // Pet health logs (depends on pets + users)
            HealthSeeder::class,

            // Misc: blocks (standalone, depends on users)
            BlockSeeder::class,
        ]);
    }
}
