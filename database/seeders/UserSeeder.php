<?php

namespace Database\Seeders;

use App\Models\Identity\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

class UserSeeder extends Seeder
{
    private const TARGET_USER_COUNT = 20;

    /**
     * @var list<string>
     */
    private const USER_NAMES = [
        'Ava Carter',
        'Noah Bennett',
        'Mia Sullivan',
        'Liam Foster',
        'Zoe Morgan',
        'Owen Brooks',
        'Ivy Parker',
        'Mason Turner',
        'Nora Hayes',
        'Eli Ward',
        'Ruby Price',
        'Leo Cooper',
        'Chloe Graham',
        'Jack Hudson',
        'Luna Brooks',
        'Caleb Fisher',
        'Aria Vaughn',
        'Isaac Palmer',
        'Hazel Reed',
        'Ethan Wells',
    ];

    /**
     * Seed the application's users.
     */
    public function run(): void
    {
        $faker = fake();
        $faker->seed(20260221);

        foreach (self::USER_NAMES as $index => $name) {
            $number = str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
            $baseUsername = (string) Str::of(Str::snake($name))
                ->replaceMatches('/[^a-z0-9_]/', '')
                ->trim('_')
                ->limit(28, '');

            $username = ($baseUsername !== '' ? $baseUsername : 'petlover').$number;

            User::factory()->create([
                'name' => $name,
                'username' => $username,
                'email' => sprintf('user%02d@larapetssocnet.test', $index + 1),
                'password' => 'password',
                'bio' => $index % 3 === 0 ? null : $faker->sentence($faker->numberBetween(6, 12)),
                'bio_html' => null,
                'avatar_path' => null,
                'cover_photo_path' => null,
                'profile_photo_path' => null,
                'followers_count' => 0,
                'following_count' => 0,
                'follow_requests_count' => 0,
                'following_pets_count' => 0,
                'pets_count' => 0,
                'posts_count' => 0,
                'blocked_users_count' => 0,
                'blocked_by_count' => 0,
            ]);
        }

        if (User::query()->count() !== self::TARGET_USER_COUNT) {
            throw new RuntimeException('UserSeeder expected exactly 20 users.');
        }
    }
}
