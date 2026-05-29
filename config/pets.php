<?php

declare(strict_types=1);

return [
    'species' => [
        'dog' => [
            'label' => 'Dog',
            'icon' => '🐕',
            'gradient' => 'from-[#F6D2A8] via-[#F4A261] to-[#A4572B]',
        ],
        'cat' => [
            'label' => 'Cat',
            'icon' => '🐈',
            'gradient' => 'from-[#F4C7AB] via-[#D98F73] to-[#7F4F3A]',
        ],
        'rabbit' => [
            'label' => 'Rabbit',
            'icon' => '🐇',
            'gradient' => 'from-[#F5E6C8] via-[#C9D8A8] to-[#6F8F64]',
        ],
        'bird' => [
            'label' => 'Bird',
            'icon' => '🐦',
            'gradient' => 'from-[#B9D8D6] via-[#64A6A0] to-[#2F5D62]',
        ],
        'reptile' => [
            'label' => 'Reptile',
            'icon' => '🦎',
            'gradient' => 'from-[#D7D7A8] via-[#8F9F62] to-[#4F5D32]',
        ],
        'fish' => [
            'label' => 'Fish',
            'icon' => '🐠',
            'gradient' => 'from-[#B8D9E8] via-[#5AA6B8] to-[#2D5C73]',
        ],
        'guinea_pig' => [
            'label' => 'Guinea Pig',
            'icon' => '🐹',
            'gradient' => 'from-[#F0D4B8] via-[#B77957] to-[#6E493B]',
        ],
        'hamster' => [
            'label' => 'Hamster',
            'icon' => '🐹',
            'gradient' => 'from-[#F6E0B9] via-[#D19A58] to-[#815D32]',
        ],
        'ferret' => [
            'label' => 'Ferret',
            'icon' => '🐾',
            'gradient' => 'from-[#D7C5B0] via-[#9B7A5C] to-[#4D3B2D]',
        ],
        'horse' => [
            'label' => 'Horse',
            'icon' => '🐴',
            'gradient' => 'from-[#E7C6A5] via-[#A66B4C] to-[#513A2E]',
        ],
        'other' => [
            'label' => 'Other',
            'icon' => '🐾',
            'gradient' => 'from-[#E7D7C5] via-[#B58A6A] to-[#5F4A3D]',
        ],
    ],

    'visibility' => [
        'public' => 'Public',
        'followers_only' => 'Followers Only',
        'private' => 'Private',
    ],

    'health_statuses' => ['yes', 'no', 'unknown'],

    'personality_tags' => [
        'max' => 5,
        'min_length' => 2,
        'max_length' => 30,
        'suggestions' => [
            'playful',
            'calm',
            'energetic',
            'shy',
            'friendly',
            'independent',
            'cuddly',
            'stubborn',
            'clever',
            'gentle',
            'mischievous',
            'protective',
            'loyal',
            'adventurous',
            'lazy',
            'vocal',
            'quiet',
            'sociable',
            'anxious',
            'fearless',
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

    'birthday' => [
        'notification_time' => env('PET_BIRTHDAY_NOTIFICATION_TIME', '08:00'),
        'post_templates' => [
            '{pet} turns {age} today!',
            'It is {pet} birthday. They turn {age} today!',
            'A big PetSocial birthday cheer for {pet}, who turns {age} today!',
        ],
    ],

    'health_reminders' => [
        'notification_time' => env('PET_HEALTH_REMINDER_NOTIFICATION_TIME', '09:00'),
    ],
];
