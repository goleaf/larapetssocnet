<?php

namespace App\Models\Content;

use App\Models\Identity\User;
use Database\Factories\Content\CommentThreadSubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UseFactory(CommentThreadSubscriptionFactory::class)]
#[Fillable([
    'user_id',
    'post_id',
    'root_comment_id',
    'subscribed_at',
    'unsubscribed_at',
])]
class CommentThreadSubscription extends Model
{
    /** @use HasFactory<CommentThreadSubscriptionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'subscribed_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * @return BelongsTo<Comment, $this>
     */
    public function rootComment(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'root_comment_id');
    }
}
