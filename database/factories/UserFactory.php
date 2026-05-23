<?php

namespace Database\Factories;

use App\Enums\ProfileTheme;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $username = (string) Str::of(fake()->unique()->userName())
            ->lower()
            ->replaceMatches('/[^a-z0-9_]/', '')
            ->trim('_');

        if ($username === '') {
            $username = 'petlover_'.fake()->unique()->numerify('###');
        }

        $username = (string) Str::of($username)->limit(30, '');
        $birthdate = fake()->optional(0.75)->date();

        return [
            'name' => fake()->name(),
            'display_name' => null,
            'username' => $username,
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'bio' => fake()->optional()->sentence(),
            'bio_html' => fake()->optional(0.4)->paragraph(),
            'headline' => fake()->optional(0.4)->sentence(6),
            'pronouns' => fake()->optional(0.3)->randomElement(['she/her', 'he/him', 'they/them', 'she/they', 'he/they']),
            'avatar_path' => fake()->optional(0.35)->imageUrl(640, 640, 'pets', true),
            'cover_photo_path' => fake()->optional(0.15)->imageUrl(1280, 720, 'pets', true),
            'cover_photo_position' => 50,
            'profile_photo_path' => fake()->optional(0.2)->imageUrl(640, 640, 'pets', true),
            'city' => fake()->optional(0.8)->city(),
            'country_code' => fake()->optional(0.8)->countryCode(),
            'location' => fake()->optional(0.8)->city(),
            'location_lat' => fake()->optional(0.75)->latitude(),
            'location_lng' => fake()->optional(0.75)->longitude(),
            'website' => fake()->optional(0.4)->url(),
            'social_links' => fake()->optional(0.2)->randomElement([
                ['twitter' => 'https://x.com/'.Str::of(fake()->userName())->replaceMatches('/[^A-Za-z0-9_]/', '_')->limit(15, '')],
                ['instagram' => 'https://instagram.com/'.fake()->userName()],
                ['website' => 'https://'.fake()->domainName()],
            ]),
            'locale' => fake()->optional(0.5)->randomElement(['en', 'en_US', 'en_GB', 'lt_LT']),
            'timezone' => fake()->optional(0.5)->timezone(),
            'gender' => fake()->optional(0.7)->randomElement(['male', 'female', 'other', 'prefer_not_to_say']),
            'gender_custom' => fake()->optional(0.05)->word(),
            'birthdate' => $birthdate,
            'birth_date' => $birthdate,
            'flags' => fake()->optional(0.1)->randomElement(['verified', 'staff', 'early_access']),
            'is_verified' => false,
            'profile_completed_at' => null,
            'is_banned' => false,
            'ban_reason' => null,
            'is_private' => false,
            'privacy_display_email' => fake()->boolean(10),
            'privacy_display_location' => fake()->boolean(75),
            'privacy_display_birthdate' => fake()->boolean(20),
            'privacy_display_last_seen' => fake()->boolean(80),
            'profile_visibility' => 'public',
            'profile_theme' => ProfileTheme::default()->value,
            'messaging_permission' => 'everyone',
            'pets_visibility' => 'everyone',
            'groups_visibility' => 'everyone',
            'show_in_explore' => true,
            'open_following' => true,
            'notification_preferences' => null,
            'password_changed_at' => null,
            'last_seen_at' => fake()->dateTimeBetween('-7 days', 'now'),
            'last_login_at' => null,
            'scheduled_deletion_at' => null,
            'deactivated_at' => null,
            'deactivation_reason' => null,
            'suspended_until' => null,
            'suspension_reason' => null,
            'onboarding_step' => fake()->randomElement(['welcome', 'profile', 'pets', 'complete']),
            'onboarding_completed_at' => fake()->optional(0.65)->dateTimeBetween('-30 days', 'now'),
            'interests_text' => implode(', ', fake()->words(fake()->numberBetween(3, 7))),
            'followers_count' => 0,
            'following_count' => 0,
            'follow_requests_count' => 0,
            'following_pets_count' => 0,
            'pets_count' => 0,
            'posts_count' => 0,
            'photos_count' => 0,
            'scheduled_posts_count' => 0,
            'post_reactions_received_count' => 0,
            'post_comments_received_count' => 0,
            'last_post_created_at' => null,
            'blocked_users_count' => 0,
            'blocked_by_count' => 0,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'email_verified_at' => null,
        ]);
    }
}
