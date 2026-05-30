<?php

namespace App\Http\Controllers\Discovery;

use App\Http\Controllers\Controller;
use App\Models\Activities\Event;
use App\Models\Content\Hashtag;
use App\Models\Content\Post;
use App\Models\Groups\Group;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Support\Search\SearchInput;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function __invoke(Request $request): View
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
            'type' => ['nullable', 'string', 'max:32'],
        ]);

        $query = SearchInput::normalize($request->string('q')->toString());
        $type = (string) $request->string('type', 'users');

        $allowedTypes = ['users', 'pets', 'posts', 'groups', 'events', 'hashtags'];
        if (! in_array($type, $allowedTypes, true)) {
            $type = 'users';
        }

        $results = SearchInput::hasSearchableLength($query)
            ? $this->searchByType($type, $query)
            : $this->emptyResults();

        return view('discovery.search.index', [
            'q' => $query,
            'type' => $type,
            'types' => $allowedTypes,
            'results' => $results,
        ]);
    }

    private function searchByType(string $type, string $query): LengthAwarePaginator
    {
        $viewer = request()->user();

        return match ($type) {
            'users' => User::paginateSearchResults($viewer, $query),
            'pets' => Pet::paginateSearchResults($viewer, $query),
            'posts' => Post::paginateSearchResults($viewer, $query),
            'groups' => Group::paginateSearchResults($viewer, $query),
            'events' => Event::paginateSearchResults($query),
            'hashtags' => Hashtag::paginateSearchResults($query),
            default => User::paginateSearchResults($viewer, $query),
        };
    }

    private function emptyResults(int $perPage = 15): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, $perPage, 1, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);
    }
}
