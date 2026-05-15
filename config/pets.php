<?php

declare(strict_types=1);

return [
    'personality_tags' => [
        'max' => 10,
        'min_length' => 2,
        'max_length' => 30,
        'suggestions' => [
            'playful',
            'energetic',
            'calm',
            'shy',
            'friendly',
            'independent',
            'cuddly',
            'protective',
            'gentle',
            'silly',
            'stubborn',
            'smart',
            'loyal',
            'vocal',
            'lazy',
            'adventurous',
        ],
    ],
    'gallery' => [
        'max_photos' => 30,
        'max_upload' => 5,
        'max_file_size_kb' => 5120,
        'allowed_mimes' => ['jpg', 'jpeg', 'png', 'webp', 'gif'],
        'caption_max' => 200,
        'alt_text_max' => 140,
    ],
];
