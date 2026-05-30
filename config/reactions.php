<?php

return [
    'default' => 'paw',

    'types' => [
        'paw' => [
            'label' => 'Paw',
            'emoji' => '🐾',
            'color' => 'brown',
            'counter_column' => 'paw_count',
            'button_class' => 'border-paw/40 bg-paw-light/70 text-paw',
            'icon_class' => 'bg-paw-light text-paw',
        ],
        'love' => [
            'label' => 'Love',
            'emoji' => '❤️',
            'color' => 'pink',
            'counter_column' => 'love_count',
            'button_class' => 'border-rose/40 bg-rose-light/70 text-rose',
            'icon_class' => 'bg-rose-light text-rose',
        ],
        'haha' => [
            'label' => 'Haha',
            'emoji' => '😂',
            'color' => 'yellow',
            'counter_column' => 'haha_count',
            'button_class' => 'border-amber/40 bg-amber-light/70 text-amber',
            'icon_class' => 'bg-amber-light text-amber',
        ],
        'wow' => [
            'label' => 'Wow',
            'emoji' => '😮',
            'color' => 'orange',
            'counter_column' => 'wow_count',
            'button_class' => 'border-orange-400/50 bg-orange-100 text-orange-700',
            'icon_class' => 'bg-orange-100 text-orange-700',
        ],
        'sad' => [
            'label' => 'Sad',
            'emoji' => '😢',
            'color' => 'blue',
            'counter_column' => 'sad_count',
            'button_class' => 'border-sky/50 bg-sky-light/70 text-sky',
            'icon_class' => 'bg-sky-light text-sky',
        ],
        'angry' => [
            'label' => 'Angry',
            'emoji' => '😡',
            'color' => 'red',
            'counter_column' => 'angry_count',
            'button_class' => 'border-red-400/50 bg-red-100 text-red-700',
            'icon_class' => 'bg-red-100 text-red-700',
        ],
    ],

    'aliases' => [
        'like' => 'paw',
        'cute' => 'paw',
        'support' => 'paw',
        'care' => 'love',
        'laugh' => 'haha',
        'funny' => 'haha',
    ],

    'comment_types' => [
        'paw',
        'love',
    ],

    'notification_milestones' => [
        10,
        50,
        100,
        500,
    ],
];
