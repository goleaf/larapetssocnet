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

        if ($query === '') {
            return match ($type) {
                'users' => User::query()->discoverable()->notBlockedFor($viewer)->latest()->paginate(15),
                'pets' => Pet::query()->latest()->paginate(15),
                'posts' => Post::query()->latest()->paginate(15),
                'groups' => Group::query()->latest()->paginate(15),
                'events' => Event::query()->latest()->paginate(15),
                'hashtags' => Hashtag::query()->latest()->paginate(15),
                default => User::query()->latest()->paginate(15),
            };
        }

        return match ($type) {
            'users' => User::query()
                ->discoverable()
                ->notBlockedFor($viewer)
                ->where(function ($builder) use ($query): void {
                    $builder->where('name', 'like', "%{$query}%")
                        ->orWhere('username', 'like', "%{$query}%")
                        ->orWhere('email', 'like', "%{$query}%")
                        ->orWhere('city', 'like', "%{$query}%");
                })
                ->latest()
                ->paginate(15),
            'pets' => Pet::query()
                ->where(function ($builder) use ($query): void {
                    $builder->where('name', 'like', "%{$query}%")
                        ->orWhere('species', 'like', "%{$query}%")
                        ->orWhere('breed', 'like', "%{$query}%")
                        ->orWhere('bio', 'like', "%{$query}%");
                })
                ->latest()
                ->paginate(15),
            'posts' => Post::query()
                ->where(function ($builder) use ($query): void {
                    $builder->where('body', 'like', "%{$query}%")
                        ->orWhere('location', 'like', "%{$query}%");
                })
                ->latest()
                ->paginate(15),
            'groups' => Group::query()
                ->where(function ($builder) use ($query): void {
                    $builder->where('name', 'like', "%{$query}%")
                        ->orWhere('slug', 'like', "%{$query}%")
                        ->orWhere('description', 'like', "%{$query}%");
                })
                ->latest()
                ->paginate(15),
            'events' => Event::query()
                ->where(function ($builder) use ($query): void {
                    $builder->where('title', 'like', "%{$query}%")
                        ->orWhere('description', 'like', "%{$query}%")
                        ->orWhere('location_text', 'like', "%{$query}%");
                })
                ->latest()
                ->paginate(15),
            'hashtags' => Hashtag::query()
                ->where(function ($builder) use ($query): void {
                    $builder->where('name', 'like', "%{$query}%")
                        ->orWhere('slug', 'like', "%{$query}%");
                })
                ->latest()
                ->paginate(15),
            default => User::query()->latest()->paginate(15),
        };
    }
}
