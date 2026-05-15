<?php

namespace Database\Factories;

use App\Models\Identity\User;
use App\Models\Marketplace\Listing;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Listing>
 */
class ListingFactory extends Factory
{
    /**
     * @var array<string, list<string>>
     */
    private const CATEGORIES_BY_TYPE = [
        'sale' => ['pet', 'food', 'toy', 'accessory', 'habitat'],
        'rehoming' => ['dog', 'cat', 'bird', 'small_pet', 'reptile'],
        'wanted' => ['pet', 'supplies', 'trainer', 'sitter', 'service'],
        'service' => ['grooming', 'training', 'walking', 'boarding', 'veterinary'],
    ];

    /**
     * @var array<string, list<string>>
     */
    private const TITLE_PREFIXES = [
        'sale' => ['Selling', 'Available', 'Like new', 'Gently used', 'Bundle'],
        'rehoming' => ['Rehoming', 'Looking for a loving home for', 'Adoption support for', 'Need new home for', 'Rescue placement for'],
        'wanted' => ['Looking for', 'Wanted', 'Need recommendations for', 'Searching for', 'Need help finding'],
        'service' => ['Professional', 'Trusted', 'Experienced', 'Local', 'Certified'],
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(['sale', 'rehoming', 'wanted', 'service']);
        $category = fake()->randomElement(self::CATEGORIES_BY_TYPE[$type]);
        $title = $this->titleFor($type, $category);
        $price = in_array($type, ['rehoming', 'wanted'], true)
            ? null
            : fake()->randomFloat(2, $type === 'service' ? 15 : 35, $type === 'service' ? 400 : 4500);

        return [
            'user_id' => User::factory(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numerify('####'),
            'type' => $type,
            'category' => $category,
            'description' => fake()->optional(0.95)->paragraphs(fake()->numberBetween(1, 2), true),
            'price' => $price,
            'currency' => 'USD',
            'price_negotiable' => $price === null ? false : fake()->boolean(35),
            'location' => fake()->optional(0.9)->city().', '.fake()->stateAbbr(),
            'status' => $this->weightedStatus(),
            'views_count' => fake()->numberBetween(0, 1400),
            'pet_species' => fake()->optional(0.75)->randomElement(['dog', 'cat', 'bird', 'rabbit', 'hamster', 'reptile', 'fish']),
        ];
    }

    private function titleFor(string $type, string $category): string
    {
        $prefix = fake()->randomElement(self::TITLE_PREFIXES[$type]);

        $suffix = match ($type) {
            'sale' => match ($category) {
                'pet' => fake()->randomElement(['Puppy essentials kit', 'Cat tree and accessories', 'Starter aquarium set']),
                'food' => fake()->randomElement(['premium pet food pack', 'grain-free meal bundle', 'large food bundle']),
                'toy' => fake()->randomElement(['interactive toy set', 'chew toy bundle', 'enrichment toys']),
                'accessory' => fake()->randomElement(['travel carrier', 'leash and harness set', 'comfort bed']),
                default => fake()->randomElement(['glass terrarium', 'hamster habitat setup', 'bird cage setup']),
            },
            'rehoming' => fake()->randomElement([
                'friendly companion',
                'young pet with vaccinations',
                'well-socialized pet',
                'loving indoor pet',
            ]),
            'wanted' => fake()->randomElement([
                'pet-friendly apartment near park',
                'reliable pet sitter',
                'beginner supplies',
                'gentle trainer',
            ]),
            default => fake()->randomElement([
                'pet grooming in-home visits',
                'weekend dog walking slots',
                'boarding with daily updates',
                'positive reinforcement sessions',
            ]),
        };

        return Str::limit(trim($prefix.' '.$suffix), 120, '');
    }

    private function weightedStatus(): string
    {
        $roll = random_int(1, 100);

        if ($roll <= 20) {
            return 'draft';
        }

        if ($roll <= 80) {
            return 'active';
        }

        if ($roll <= 90) {
            return 'sold';
        }

        return 'archived';
    }
}
