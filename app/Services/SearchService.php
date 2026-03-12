<?php

namespace App\Services;

use App\Models\Group;
use App\Models\Hashtag;
use App\Models\Pet;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Str;

class SearchService
{
    /** @return array<string, mixed> */
    public function search(string $term, ?User $viewer, string $tab = 'all'): array
    {
        $clean = Str::limit(trim($term), 100);

        if (mb_strlen($clean) < 2) {
            return [];
        }

        $results = [];
        $limit = $tab === 'all' ? 3 : 20;

        if ($tab === 'all' || $tab === 'users') {
            $query = User::where('is_banned', false)
                ->where('is_private', false)
                ->search($clean)
                ->with('media')
                ->limit($limit);

            $results['users'] = $tab === 'all' ? $query->get() : $query->paginate(20);
        }

        if ($tab === 'all' || $tab === 'pets') {
            $query = Pet::query()
                ->visibleTo($viewer)
                ->where(fn ($q) => $q->where('name', 'like', "%{$clean}%")->orWhere('breed', 'like', "%{$clean}%"))
                ->with('media')
                ->limit($limit);

            $results['pets'] = $tab === 'all' ? $query->get() : $query->paginate(20);
        }

        if ($tab === 'all' || $tab === 'posts') {
            $query = Post::where('body', 'like', "%{$clean}%")
                ->where('visibility', 'public')
                ->with(['author.media', 'media'])
                ->withCount(['comments', 'reactions'])
                ->latest()
                ->limit($limit);

            $results['posts'] = $tab === 'all' ? $query->get() : $query->paginate(20);
        }

        if ($tab === 'all' || $tab === 'groups') {
            $query = Group::where('name', 'like', "%{$clean}%")
                ->where('privacy', '!=', 'secret')
                ->with('media')
                ->limit($limit);

            $results['groups'] = $tab === 'all' ? $query->get() : $query->paginate(20);
        }

        if ($tab === 'all' || $tab === 'hashtags') {
            $hashLimit = $tab === 'all' ? 6 : 30;
            $query = Hashtag::where('name', 'like', "%{$clean}%")
                ->orderByDesc('posts_count')
                ->limit($hashLimit);

            $results['hashtags'] = $tab === 'all' ? $query->get() : $query->paginate(30);
        }

        return $results;
    }
}
