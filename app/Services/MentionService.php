<?php

namespace App\Services;

use App\Models\Identity\User;

class MentionService
{
    public function parse(string $text): string
    {
        return (string) preg_replace_callback('/@(\w{3,30})/', function (array $matches): string {
            $username = strtolower($matches[1]);

            $exists = User::query()->where('username', $username)->exists();
            if (! $exists) {
                return $matches[0];
            }

            $href = username_url($username);

            return '<a href="'.$href.'" class="text-emerald-600 hover:text-emerald-700 font-medium hover:underline" title="View @'.$username.'\'s profile">@'.$username.'</a>';
        }, $text);
    }

    /**
     * @return list<string>
     */
    public function extractMentions(string $text): array
    {
        preg_match_all('/@(\w{3,30})/', $text, $matches);

        $usernames = array_values(array_unique(array_map(
            static fn (string $value): string => strtolower($value),
            $matches[1] ?? []
        )));

        return User::query()
            ->whereIn('username', $usernames)
            ->pluck('username')
            ->map(static fn (string $username): string => strtolower($username))
            ->values()
            ->all();
    }
}
