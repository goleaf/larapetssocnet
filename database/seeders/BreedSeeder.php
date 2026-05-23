<?php

namespace Database\Seeders;

use App\Models\Pets\Breed;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BreedSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->breedsBySpecies() as $species => $breeds) {
            foreach ($breeds as $breed) {
                Breed::query()->updateOrCreate([
                    'slug' => $species.'-'.Str::slug($breed),
                ], [
                    'name' => $breed,
                    'species_slug' => $species,
                ]);
            }
        }
    }

    /**
     * @return array<string, list<string>>
     */
    private function breedsBySpecies(): array
    {
        return [
            'dog' => [
                'Affenpinscher',
                'Australian Shepherd',
                'Beagle',
                'Bernese Mountain Dog',
                'Border Collie',
                'Boston Terrier',
                'Boxer',
                'Bulldog',
                'Cavalier King Charles Spaniel',
                'Chihuahua',
                'Cocker Spaniel',
                'Dachshund',
                'French Bulldog',
                'German Shepherd',
                'Golden Retriever',
                'Great Dane',
                'Labrador Retriever',
                'Maltese',
                'Poodle',
                'Siberian Husky',
            ],
            'cat' => [
                'Abyssinian',
                'American Shorthair',
                'Bengal',
                'Birman',
                'British Shorthair',
                'Burmese',
                'Devon Rex',
                'Domestic Longhair',
                'Domestic Shorthair',
                'Maine Coon',
                'Norwegian Forest Cat',
                'Persian',
                'Ragdoll',
                'Russian Blue',
                'Scottish Fold',
                'Siamese',
                'Sphynx',
                'Tonkinese',
                'Tortoiseshell',
                'Turkish Angora',
            ],
            'rabbit' => [
                'American Fuzzy Lop',
                'Californian',
                'Dutch',
                'English Angora',
                'English Lop',
                'Flemish Giant',
                'Harlequin',
                'Holland Lop',
                'Lionhead',
                'Mini Lop',
                'Mini Rex',
                'Netherland Dwarf',
                'New Zealand',
                'Rex',
            ],
            'bird' => [
                'African Grey',
                'Amazon Parrot',
                'Budgerigar',
                'Canary',
                'Cockatiel',
                'Cockatoo',
                'Conure',
                'Dove',
                'Finch',
                'Lovebird',
                'Macaw',
                'Parakeet',
                'Quaker Parrot',
            ],
            'reptile' => [
                'Ball Python',
                'Bearded Dragon',
                'Blue-Tongued Skink',
                'Boa Constrictor',
                'Corn Snake',
                'Crested Gecko',
                'Greek Tortoise',
                'Green Iguana',
                'Leopard Gecko',
                'Red-Eared Slider',
                'Russian Tortoise',
                'Veiled Chameleon',
            ],
            'fish' => [
                'Angelfish',
                'Betta',
                'Cichlid',
                'Clownfish',
                'Corydoras',
                'Discus',
                'Goldfish',
                'Gourami',
                'Guppy',
                'Koi',
                'Molly',
                'Neon Tetra',
                'Platy',
                'Zebrafish',
            ],
            'guinea_pig' => [
                'Abyssinian',
                'American',
                'Coronet',
                'Peruvian',
                'Silkie',
                'Skinny Pig',
                'Teddy',
                'Texel',
                'White Crested',
            ],
            'hamster' => [
                'Campbell Dwarf',
                'Chinese Hamster',
                'Roborovski',
                'Syrian Hamster',
                'Winter White Dwarf',
            ],
            'ferret' => [
                'Albino',
                'Black Sable',
                'Blaze',
                'Champagne',
                'Chocolate',
                'Cinnamon',
                'Panda',
                'Sable',
            ],
            'horse' => [
                'American Paint Horse',
                'American Quarter Horse',
                'Andalusian',
                'Appaloosa',
                'Arabian',
                'Clydesdale',
                'Friesian',
                'Haflinger',
                'Morgan',
                'Mustang',
                'Shetland Pony',
                'Thoroughbred',
                'Welsh Pony',
            ],
            'other' => [
                'Companion Animal',
                'Rescue Animal',
                'Small Mammal',
            ],
        ];
    }
}
