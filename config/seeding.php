<?php

return [
    'profile' => env('SEED_PROFILE'),

    'performance_confirmation' => filter_var((string) env('SEED_PERFORMANCE_CONFIRMATION', false), FILTER_VALIDATE_BOOLEAN),

    'profiles' => [
        'tiny' => [
            'users' => 3,
            'pets' => 3,
            'posts' => 5,
            'comments' => 5,
            'likes' => 5,
            'content_posts' => 0,
            'adoptable_pets' => 0,
            'social' => [
                'follows_per_user' => 1,
                'pet_follows_per_user' => 0,
                'blocks' => 0,
            ],
            'media' => [
                'enabled' => false,
                'users_with_media' => 0,
                'pets_with_media' => 0,
            ],
            'seed_hashtags' => false,
            'seed_reaction_rows' => false,
        ],
        'demo' => [
            'users' => 20,
            'pets' => 40,
            'posts' => 150,
            'comments' => 300,
            'likes' => 600,
            'content_posts' => 0,
            'adoptable_pets' => 0,
            'social' => [
                'follows_per_user' => 8,
                'pet_follows_per_user' => 3,
                'blocks' => 12,
            ],
            'media' => [
                'enabled' => true,
                'users_with_media' => 6,
                'pets_with_media' => 6,
            ],
            'seed_hashtags' => true,
            'seed_reaction_rows' => true,
        ],
        'test' => [
            'users' => 12,
            'pets' => 12,
            'posts' => 24,
            'comments' => 24,
            'likes' => 24,
            'content_posts' => 0,
            'adoptable_pets' => 0,
            'social' => [
                'follows_per_user' => 4,
                'pet_follows_per_user' => 1,
                'blocks' => 2,
            ],
            'media' => [
                'enabled' => true,
                'users_with_media' => 4,
                'pets_with_media' => 4,
            ],
            'seed_hashtags' => false,
            'seed_reaction_rows' => false,
        ],
        'performance' => [
            'users' => 500,
            'pets' => 1000,
            'posts' => 10000,
            'comments' => 50000,
            'likes' => 50000,
            'content_posts' => 0,
            'adoptable_pets' => 0,
            'social' => [
                'follows_per_user' => 12,
                'pet_follows_per_user' => 4,
                'blocks' => 40,
            ],
            'media' => [
                'enabled' => false,
                'users_with_media' => 0,
                'pets_with_media' => 0,
            ],
            'seed_hashtags' => false,
            'seed_reaction_rows' => false,
        ],
    ],
];
