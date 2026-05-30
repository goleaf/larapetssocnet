<?php

namespace App\Models\Social;

use App\Models\Content\Post;
use App\Models\Identity\User;
use Database\Factories\Social\FeedItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UseFactory(FeedItemFactory::class)]
#[Fillable([
    'user_id',
    'post_id',
    'source_type',
    'source_id',
    'post_created_at',
])]
class FeedItem extends Model
{
    /** @use HasFactory<FeedItemFactory> */
    use HasFactory;

    public const SOURCE_SELF = 'self';

    public const SOURCE_USER = 'user';

    public const SOURCE_PET = 'pet';

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'post_id' => 'integer',
            'source_id' => 'integer',
            'post_created_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
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
