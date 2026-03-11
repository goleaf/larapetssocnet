<?php

namespace App\Events;

use App\Models\Like;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PostLiked
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Like $like,
        public readonly User $user,
        public readonly Post $post,
    ) {}
}
