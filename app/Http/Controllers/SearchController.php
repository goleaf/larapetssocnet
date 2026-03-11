<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Group;
use App\Models\Hashtag;
use App\Models\Pet;
use App\Models\Post;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function __invoke(Request $request): View
    {
        $query = trim((string) $request->string('q'));
        $type = (string) $request->string('type', 'users');

        $allowedTypes = ['users', 'pets', 'posts', 'groups', 'events', 'hashtags'];
        if (! in_array($type, $allowedTypes, true)) {
            $type = 'users';
        }

        $results = $this->searchByType($type, $query);

        return view('search.index', [
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
            'pets' => Pet::paginateSearchResults($query),
            'posts' => Post::paginateSearchResults($viewer, $query),
            'groups' => Group::paginateSearchResults($viewer, $query),
            'events' => Event::paginateSearchResults($query),
            'hashtags' => Hashtag::paginateSearchResults($query),
            default => User::paginateSearchResults($viewer, $query),
        };
    }
}
