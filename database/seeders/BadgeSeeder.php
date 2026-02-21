<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BadgeSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $badges = [
            ['name' => 'First Post', 'condition_type' => 'posts_count', 'condition_value' => 1],
            ['name' => 'Social Butterfly', 'condition_type' => 'followers_count', 'condition_value' => 10],
            ['name' => 'Pet Lover', 'condition_type' => 'pets_count', 'condition_value' => 3],
            ['name' => 'Photographer', 'condition_type' => 'manual', 'condition_value' => null],
            ['name' => 'Storyteller', 'condition_type' => 'posts_count', 'condition_value' => 20],
            ['name' => 'Community Leader', 'condition_type' => 'manual', 'condition_value' => null],
            ['name' => 'Top Seller', 'condition_type' => 'manual', 'condition_value' => null],
            ['name' => 'Contest Winner', 'condition_type' => 'manual', 'condition_value' => null],
            ['name' => 'Verified Owner', 'condition_type' => 'manual', 'condition_value' => null],
            ['name' => 'Early Adopter', 'condition_type' => 'manual', 'condition_value' => null],
        ];

        $rows = array_map(static fn (array $badge) => [
            'name' => $badge['name'],
            'slug' => Str::slug($badge['name']),
            'description' => $badge['name'].' badge',
            'icon' => null,
            'condition_type' => $badge['condition_type'],
            'condition_value' => $badge['condition_value'],
            'created_at' => $now,
            'updated_at' => $now,
        ], $badges);

        DB::table('badges')->insertOrIgnore($rows);
    }
}
