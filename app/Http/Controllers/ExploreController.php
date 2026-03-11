<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExploreController extends Controller
{
    public function index(Request $request): View
    {
        $viewer = $request->user();
        $type = $request->string('type')->toString();
        $type = in_array($type, ['all', 'photos', 'videos', 'trending'], true) ? $type : 'all';
        $search = trim($request->string('q')->toString());

        $posts = Post::paginateExploreResults($viewer, $type, $search);

        return view('explore.index', [
            'posts' => $posts,
            'type' => $type,
            'search' => $search,
        ]);
    }
}
