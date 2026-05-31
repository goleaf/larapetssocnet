<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Identity\User;
use App\Support\Seeding\SeedProfile;
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
        $profile = SeedProfile::fromConfig();

        if ($profile === null) {
            $this->runLegacy();

            return;
        }

        $this->runProfile($profile);
    }

    private function runLegacy(): void
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

    private function runProfile(SeedProfile $profile): void
    {
        $count = $profile->users();

        if ($count < 1) {
            return;
        }

        $faker = fake();
        $faker->seed(20260221);

        $mediaEnabled = $profile->mediaEnabled();

        for ($index = 0; $index < $count; $index++) {
            $number = str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
            $override = $this->profileUserOverride($profile, $index);

            $baseUsername = (string) Str::of(Str::snake(($override['name'] ?? self::USER_NAMES[$index % count(self::USER_NAMES)])))
                ->replaceMatches('/[^a-z0-9_]/', '')
                ->trim('_')
                ->limit(28, '');

            $username = ($baseUsername !== '' ? $baseUsername : 'petlover').$number;
            $email = (string) ($override['email'] ?? sprintf('seed-%s-user%02d@larapetssocnet.test', $profile->value, $index + 1));

            User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => (string) ($override['name'] ?? self::USER_NAMES[$index % count(self::USER_NAMES)]),
                    'username' => $override['username'] ?? $username,
                    'email_verified_at' => now(),
                    'password' => 'password',
                    'bio' => $override['bio'] ?? ($index % 3 === 0 ? null : $faker->sentence($faker->numberBetween(6, 12))),
                    'bio_html' => null,
                    'avatar_path' => $mediaEnabled && $index < $profile->usersWithMedia() ? sprintf('seed-media/users/user-%s-avatar.jpg', $profile->value.'-'.strtolower($username)) : null,
                    'cover_photo_path' => $mediaEnabled && $index < $profile->usersWithMedia() ? sprintf('seed-media/users/user-%s-cover.jpg', $profile->value.'-'.strtolower($username)) : null,
                    'cover_photo_position' => 50,
                    'profile_photo_path' => $mediaEnabled && $index < $profile->usersWithMedia() ? sprintf('seed-media/users/user-%s-profile.jpg', $profile->value.'-'.strtolower($username)) : null,
                    'followers_count' => 0,
                    'following_count' => 0,
                    'follow_requests_count' => 0,
                    'following_pets_count' => 0,
                    'pets_count' => 0,
                    'posts_count' => 0,
                    'blocked_users_count' => 0,
                    'blocked_by_count' => 0,
                    'is_private' => (bool) ($override['is_private'] ?? false),
                    'privacy_display_email' => (bool) ($override['privacy_display_email'] ?? false),
                    'privacy_display_location' => (bool) ($override['privacy_display_location'] ?? false),
                    'privacy_display_birthdate' => (bool) ($override['privacy_display_birthdate'] ?? false),
                    'profile_visibility' => (string) ($override['profile_visibility'] ?? 'public'),
                ]
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function profileUserOverride(SeedProfile $profile, int $index): array
    {
        if ($profile !== SeedProfile::Test) {
            return [];
        }

        return match ($index) {
            0 => [
                'name' => 'Test Public Owner',
                'username' => 'test_seed_public',
                'email' => 'seed-test-public@larapetssocnet.test',
                'bio' => 'Deterministic public user for seed tests.',
                'privacy_display_email' => true,
                'profile_visibility' => 'public',
                'is_private' => false,
            ],
            1 => [
                'name' => 'Test Private Profile',
                'username' => 'test_seed_private',
                'email' => 'seed-test-private@larapetssocnet.test',
                'bio' => 'Deterministic private user for visibility fixtures.',
                'privacy_display_email' => false,
                'profile_visibility' => 'private',
                'is_private' => true,
            ],
            2 => [
                'name' => 'Test Media Account',
                'username' => 'test_seed_media',
                'email' => 'seed-test-media@larapetssocnet.test',
                'bio' => 'Deterministic media fixture user.',
                'privacy_display_email' => true,
                'profile_visibility' => 'public',
                'is_private' => false,
                'avatar_path' => 'seed-media/users/test-media-avatar.jpg',
                'cover_photo_path' => 'seed-media/users/test-media-cover.jpg',
                'profile_photo_path' => 'seed-media/users/test-media-profile.jpg',
            ],
            default => [],
        };
    }
}
