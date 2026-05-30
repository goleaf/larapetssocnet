<?php

namespace App\Services;

use App\Models\Content\Hashtag;
use App\Models\Content\Post;
use App\Models\Groups\Group;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Support\Hashtags\HashtagNormalizer;
use App\Support\Search\SearchInput;

class SearchService
{
    /** @return array<string, mixed> */
    public function search(string $term, ?User $viewer, string $tab = 'all'): array
    {
        $clean = SearchInput::normalize($term);

        if (! SearchInput::hasSearchableLength($clean)) {
            return [];
        }

        $results = [];
        $limit = $tab === 'all' ? 3 : 20;
        $containsPattern = SearchInput::containsPattern($clean);

        if ($tab === 'all' || $tab === 'users') {
            $query = User::query()
                ->visibleTo($viewer)
                ->where(function ($discoverableQuery): void {
                    $discoverableQuery
                        ->whereNull('users.show_in_explore')
                        ->orWhere('users.show_in_explore', true);
                })
                ->search($clean)
                ->with('media')
                ->limit($limit);

            $results['users'] = $tab === 'all' ? $query->get() : $query->paginate(20);
        }

        if ($tab === 'all' || $tab === 'pets') {
            $query = Pet::query()
                ->visibleTo($viewer)
                ->where(fn ($q) => $q->where('name', 'like', $containsPattern)->orWhere('breed', 'like', $containsPattern))
                ->with('media')
                ->limit($limit);

            $results['pets'] = $tab === 'all' ? $query->get() : $query->paginate(20);
        }

        if ($tab === 'all' || $tab === 'posts') {
            $query = Post::query()
                ->published()
                ->visibleTo($viewer)
                ->where('body', 'like', $containsPattern)
                ->with(['author.media', 'media'])
                ->withCount(['comments', 'reactions'])
                ->latest()
                ->limit($limit);

            $results['posts'] = $tab === 'all' ? $query->get() : $query->paginate(20);
        }

        if ($tab === 'all' || $tab === 'groups') {
            $query = Group::query()
                ->where('name', 'like', $containsPattern)
                ->where('privacy', '!=', 'secret')
                ->with('media')
                ->limit($limit);

            $results['groups'] = $tab === 'all' ? $query->get() : $query->paginate(20);
        }

        if ($tab === 'all' || $tab === 'hashtags') {
            $hashLimit = $tab === 'all' ? 6 : 30;
            $normalizer = new HashtagNormalizer;
            $normalized = $normalizer->normalizeFromInput($clean);
            $term = $normalized ?? $clean;

            $query = Hashtag::query()
                ->search($term)
                ->orderByDesc('posts_count')
                ->limit($hashLimit);

            $results['hashtags'] = $tab === 'all' ? $query->get() : $query->paginate(30);
        }

        return $results;
    }
}
