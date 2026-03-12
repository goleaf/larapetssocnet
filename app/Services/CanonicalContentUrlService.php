<?php

namespace App\Services;

use App\Models\Post;
use Illuminate\Support\Facades\Route;

class CanonicalContentUrlService
{
    public function post(Post $post): string
    {
        if (Route::has('posts.show')) {
            return route('posts.show', ['post' => $post]);
        }

        return url('/');
    }
}
