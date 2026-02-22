<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ListingSeeder extends Seeder
{
    private const TARGET_LISTING_COUNT = 50;

    private const CATEGORIES = [
        'dogs',
        'cats',
        'birds',
        'fish',
        'reptiles',
        'small_animals',
        'supplies',
        'services',
    ];

    private const TYPES = ['sale', 'rehoming', 'wanted', 'service'];

    private const SPECIES = ['dog', 'cat', 'bird', 'fish', 'rabbit', 'hamster', 'reptile', null];

    public function run(): void
    {
        $userIds = DB::table('users')->pluck('id')->all();

        if ($userIds === []) {
            return;
        }

        $faker = fake();
        $faker->seed(20260223);

        $listingIds = [];

        for ($i = 0; $i < self::TARGET_LISTING_COUNT; $i++) {
            $userId = $userIds[array_rand($userIds)];
            $type = self::TYPES[array_rand(self::TYPES)];
            $category = self::CATEGORIES[array_rand(self::CATEGORIES)];
            $species = self::SPECIES[array_rand(self::SPECIES)];
            $price = in_array($type, ['adoption', 'rehome'], true) && $faker->boolean(50)
                ? null
                : $faker->randomFloat(2, 10, 2000);

            $title = $faker->sentence($faker->numberBetween(3, 7));
            $createdAt = Carbon::instance($faker->dateTimeBetween('-90 days', 'now'));

            $listingIds[] = DB::table('listings')->insertGetId([
                'user_id' => $userId,
                'title' => rtrim($title, '.'),
                'slug' => Str::slug($title).'-'.random_int(1000, 9999),
                'type' => $type,
                'category' => $category,
                'description' => $faker->paragraph($faker->numberBetween(2, 4)),
                'price' => $price,
                'currency' => 'USD',
                'price_negotiable' => $price !== null && $faker->boolean(30),
                'location' => $faker->boolean(85) ? $faker->city() : null,
                'status' => $faker->randomElement(['active', 'active', 'active', 'draft', 'sold', 'archived']),
                'views_count' => $faker->numberBetween(0, 500),
                'pet_species' => $species,
                'deleted_at' => null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }

        $imageRows = [];

        foreach ($listingIds as $listingId) {
            $imageCount = $faker->numberBetween(1, 4);

            for ($j = 0; $j < $imageCount; $j++) {
                $imageRows[] = [
                    'listing_id' => $listingId,
                    'file_path' => '',
                    'order' => $j,
                    'is_cover' => $j === 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        foreach (array_chunk($imageRows, 500) as $chunk) {
            DB::table('listing_images')->insert($chunk);
        }
    }
}
