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
            RoleSeeder::class,
            BadgeSeeder::class,
            UserSeeder::class,
            PetSeeder::class,
            SocialSeeder::class,
            ContentSeeder::class,
            GroupSeeder::class,
            EventSeeder::class,
            MarketplaceSeeder::class,
            HealthSeeder::class,
            ContestSeeder::class,
        ]);
    }
}
