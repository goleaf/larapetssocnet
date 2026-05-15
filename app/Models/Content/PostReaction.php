<?php

namespace App\Models\Content;

use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'post_id',
    'user_id',
    'type',
])]
class PostReaction extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    public const TYPES = [
        'love',
        'cute',
        'funny',
        'wow',
        'sad',
        'support',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
