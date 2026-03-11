<?php

namespace Database\Seeders;

use App\Models\ReservedUsername;
use Illuminate\Database\Seeder;

class ReservedUsernameSeeder extends Seeder
{
    public function run(): void
    {
        $system = [
            'admin', 'administrator', 'api', 'app', 'auth', 'blog', 'cdn',
            'contact', 'dashboard', 'explore', 'feed', 'groups', 'events',
            'hashtags', 'help', 'home', 'inbox', 'login', 'logout',
            'marketplace', 'messages', 'notifications', 'onboarding',
            'pets', 'post', 'posts', 'profile', 'register', 'saved',
            'search', 'settings', 'signup', 'support', 'tips', 'user', 'users',
            'welcome',
        ];

        $system = array_values(array_unique(array_merge(
            $system,
            is_array(config('usernames.reserved')) ? config('usernames.reserved') : []
        )));

        $brand = [
            'petsocial', 'larapets', 'petbook', 'petgram', 'pawsbook', 'pawsocial',
        ];

        $conduct = array_values(array_unique(array_map(
            static fn (string $item): string => strtolower(trim($item)),
            config('reserved_usernames.conduct', [])
        )));

        foreach ($system as $username) {
            ReservedUsername::query()->updateOrCreate(
                ['username' => strtolower($username)],
                ['reason' => 'system', 'created_at' => now()]
            );
        }

        foreach ($brand as $username) {
            ReservedUsername::query()->updateOrCreate(
                ['username' => strtolower($username)],
                ['reason' => 'brand', 'created_at' => now()]
            );
        }

        foreach ($conduct as $username) {
            ReservedUsername::query()->updateOrCreate(
                ['username' => strtolower($username)],
                ['reason' => 'conduct', 'created_at' => now()]
            );
        }
    }
}
