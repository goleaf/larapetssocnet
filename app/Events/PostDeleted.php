<?php

namespace App\Events;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PostDeleted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Post $post,
        public readonly User $deletedBy,
    ) {}
}
