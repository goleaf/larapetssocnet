<?php

declare(strict_types=1);

return [
    'min_length' => 3,
    'max_length' => 30,
    'pattern' => '/^[a-z0-9_-]+$/',
    'strip_pattern' => '/[^a-z0-9_-]/',
    'disallow_numeric_only' => true,
    'redirect_ttl_days' => 36500,
    'cooldown_days' => 30,
    'reserve_old_usernames' => true,
    'reserved' => [
        'about', 'abuse', 'account', 'admin', 'administrator', 'api', 'app', 'assets', 'auth', 'badge', 'badges',
        'banned', 'blog', 'contact', 'contests', 'dashboard', 'data', 'dev', 'explore', 'events',
        'feed', 'groups', 'help', 'home', 'inbox', 'login', 'logout', 'marketplace', 'messages',
        'moderation', 'notifications', 'official', 'onboarding', 'password', 'pets', 'photos', 'post',
        'posts', 'privacy', 'profile', 'register', 'reports', 'reset', 'root', 'saved', 'search',
        'security', 'settings', 'signup', 'staff', 'support', 'system', 'terms', 'tips', 'user',
        'users', 'verify', 'www', 'mail', 'null', 'undefined',
    ],
];
