<?php

declare(strict_types=1);

return [
    'min_length' => 3,
    'max_length' => 30,
    'pattern' => '/^[a-z0-9_]+$/',
    'strip_pattern' => '/[^a-z0-9_]/',
    'disallow_numeric_only' => true,
    'redirect_ttl_days' => 36500,
    'cooldown_days' => 30,
    'reserve_old_usernames' => true,
    'reserved' => [
        'about', 'account', 'admin', 'administrator', 'api', 'app', 'assets', 'auth', 'badge', 'badges',
        'banned', 'blog', 'contact', 'contests', 'dashboard', 'data', 'dev', 'explore', 'events',
        'feed', 'groups', 'help', 'home', 'inbox', 'login', 'logout', 'marketplace', 'messages',
        'moderation', 'notifications', 'onboarding', 'pets', 'photos', 'post', 'posts', 'profile',
        'register', 'reports', 'root', 'saved', 'search', 'security', 'settings', 'signup', 'support',
        'system', 'tips', 'user', 'users', 'www', 'mail', 'null', 'undefined',
    ],
];
