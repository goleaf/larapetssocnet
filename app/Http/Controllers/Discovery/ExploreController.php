<?php

namespace App\Http\Controllers\Discovery;

use App\Http\Controllers\Controller;
use App\Models\Content\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\View\View;

class ExploreController extends Controller
{
    public function index(Request $request): View
    {
        $viewer = $request->user();
        $type = Arr::first(Arr::onlyValues(
            [$request->string('type')->toString()],
            ['all', 'photos', 'videos', 'trending'],
            strict: true,
        ), default: 'all');

        $search = Arr::first(Arr::exceptValues(
            [trim($request->string('q')->toString())],
            [''],
            strict: true,
        ), default: '');

        $posts = Post::paginateExploreResults($viewer, $type, $search);

        return view('discovery.explore.index', [
            'posts' => $posts,
            'type' => $type,
            'search' => $search,
        ]);
    }
}
