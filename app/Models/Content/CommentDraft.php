<?php

namespace App\Models\Content;

use App\Models\Identity\User;
use Database\Factories\Content\CommentDraftFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UseFactory(CommentDraftFactory::class)]
#[Fillable([
    'user_id',
    'post_id',
    'body',
    'gif_url',
    'gif_preview_url',
    'gif_title',
    'gif_provider',
    'last_autosaved_at',
])]
class CommentDraft extends Model
{
    /** @use HasFactory<CommentDraftFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'last_autosaved_at' => 'datetime',
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
}
