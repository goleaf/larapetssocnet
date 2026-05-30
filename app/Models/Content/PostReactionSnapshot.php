<?php

namespace App\Models\Content;

use Database\Factories\PostReactionSnapshotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UseFactory(PostReactionSnapshotFactory::class)]
#[Fillable([
    'post_id',
    'captured_at',
    'reactions_count',
])]
class PostReactionSnapshot extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'post_id' => 'integer',
            'captured_at' => 'datetime',
            'reactions_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
