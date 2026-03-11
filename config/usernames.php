<?php

return [
    'min_length' => 3,
    'max_length' => 30,
    'pattern' => '/^[a-z0-9_]+$/',
    'strip_pattern' => '/[^a-z0-9_]/',
    'disallow_numeric_only' => true,
    'redirect_ttl_days' => 90,
    'cooldown_days' => 30,
    'reserved' => [
        'admin', 'administrator', 'api', 'app', 'assets', 'auth', 'badge', 'badges', 'banned', 'blog',
        'contact', 'contests', 'dashboard', 'data', 'dev', 'explore', 'events', 'feed', 'groups',
        'hashtags', 'help', 'home', 'inbox', 'login', 'logout', 'marketplace', 'messages', 'notifications',
        'onboarding', 'pets', 'photos', 'post', 'posts', 'profile', 'register', 'reports', 'saved',
        'search', 'security', 'settings', 'signup', 'support', 'system', 'tips', 'user', 'users',
        'www', 'mail', 'null', 'undefined',
    ],
];
